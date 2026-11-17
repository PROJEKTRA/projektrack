# ADR-0002: Module system and plugin SDK

## Status

Accepted, 16.11.2026

## Context

Projektrack combines project management, time tracking, service reports, quotes, invoicing with e-invoicing, work management, service desk, absences, wiki, product management, licensing and optional AI. Customers enable different subsets, editions gate commercial functionality, and from 2.0 third parties publish their own modules. Requirements:

- Everything except the core is a module that can be enabled or disabled per tenant at runtime, in the admin UI, with dependencies explained in plain language.
- A disabled module exposes no navigation, routes, API endpoints, widgets or jobs, and its data is kept.
- Modules communicate only through domain events and defined contracts, never by accessing another module's tables.
- Extension points for third-party modules: menus, dashboard widgets, fields, export templates, automation actions, tax packs, payment adapters.
- Third-party modules declare capabilities that an admin approves on install; PROJEKTRA-signed modules are marked as verified.
- Feature flags complement modules for gradual rollouts.
- Everything must run on plain AMP hosting (no Composer on the server), so installing a module cannot require running Composer.
- The plugin SDK becomes public with the marketplace in 2.0, but the internal module boundaries must hold from 0.9.

## Decision

1. **In-repository modules as first-class units.** Community modules live in `/modules/<Key>`, commercial modules in `/ee/modules/<Key>`. Each contains `module.json`, a service provider, `Domain/`, `Http/`, `Database/` (migrations, factories, seeders), `lang/<locale>/`, `resources/js/`, `tests/`. Modules are autoloaded through a PSR-4 map generated at build time and shipped in the release ZIP, so no Composer is needed on the server.

2. **Own lightweight module registry in the core.** The registry discovers manifests, validates them against the `module.json` schema ([modules.md](../architecture/modules.md)), resolves the dependency graph (acyclic; topological boot order), records installation in `modules` and per-tenant state in `tenant_modules`. Enabling checks dependencies, edition entitlement and (for third-party modules) approved capabilities. Disabling is refused while enabled modules depend on it.

3. **Runtime gating per tenant.** Module service providers always register their bindings (so contracts resolve), but routes, navigation, widgets, listeners, scheduled tasks and automation triggers are registered through the registry and filtered by the current tenant's enabled set. Middleware rejects requests to routes of disabled modules. Jobs re-check module state when they run.

4. **Data ownership.** Each table belongs to exactly one module. Migrations of all installed modules run regardless of enablement, so enabling a module later never requires a schema change at runtime. Foreign keys are allowed only towards modules in `requires`; references to optional modules are soft references (indexed ULID without constraint), kept consistent by events (see [data-model.md](../architecture/data-model.md), section 1.10).

5. **Communication through events and contracts.**
   - Domain events are written to the transactional outbox and dispatched after commit (at-least-once, consumers idempotent).
   - Contracts are PHP interfaces in the core `Contracts` namespace (for example `RateResolver`, `TaxEngine`, `TimeEntryQuery`, `DocumentRenderer`, `WorkflowRuntime`, `AiGateway`). A module provides implementations; when the provider is disabled, a null implementation answers with empty results or a clear "module disabled" outcome.
   - A static architecture test (dependency rules on namespaces) fails the build if a module references another module's internal classes or tables.

6. **Extension points** are named, versioned registries owned by the core or a module: `core.navigation`, `core.dashboard_widgets`, `core.custom_field_types`, `core.export_templates`, `core.document_placeholders`, `automation.triggers`, `automation.conditions`, `automation.actions`, `workflow.conditions`, `workflow.validators`, `workflow.post_functions`, `invoices.tax_packs`, `invoices.payment_adapters`, `invoices.fiscal_adapters`, `einvoicing.transmission_channels`, `ai.providers`, `imports.sources`, `search.indexers`. Each extension point publishes a PHP interface plus, for UI, a typed Vue slot contract.

7. **Capabilities and trust.** `capabilities_required` (for example `http.outbound`, `mail.send`, `ai.invoke`, `secrets.store`) is shown to the admin before a third-party module is enabled; approval is stored and audited. Capability checks are enforced at the core service boundary (the outbound HTTP client, mailer, AI gateway, secret store and file API check the calling module's approved capabilities). PHP code runs in-process, so capabilities are a **declared and enforced-at-API contract plus review**, not a sandbox; this is stated in the SDK documentation.

8. **Signatures.** Module archives carry a checksum list signed with PROJEKTRA's Ed25519 module key. The installer verifies signature and checksums; signed modules show "Verified by PROJEKTRA", unsigned modules require an explicit admin acknowledgement and are blocked entirely in SaaS.

9. **Feature flags** (`feature_flags`, `feature_flag_overrides`) gate unfinished or gradually rolled out features inside a module, per instance, tenant or user. Flags have an owner and an expiry date; expired flags fail CI.

10. **Edition gating.** Commercial modules load only when the installed license covers their edition. When a commercial license expires, commercial modules switch to read-only, never to data loss or lock-out of Community functions.

11. **SDK roadmap.** From 0.9 all PROJEKTRA modules use exactly the public mechanisms (dogfooding the SDK). In 1.0 the SDK documentation and an example module (`modules/Example`) are published with the Community edition. In 2.0 the marketplace adds distribution, review and update channels for third-party modules.

12. **Translations per module.** Each module ships its own language files; string identifiers are prefixed with the module key. Language packs can update module strings independently of module code.

## Alternatives considered

| Alternative | Why not chosen |
|---|---|
| Generic third-party Laravel module package | Covers folder structure and loading, but not per-tenant enablement, edition gating, capabilities, signatures or dependency explanations; we would wrap most of it. May still be used internally for scaffolding commands if it fits without lock-in. |
| Composer packages installed at runtime | Requires Composer on the server; breaks the AMP profile and one-click updates. |
| Microservices per domain | Operational burden incompatible with shared hosting; distributed transactions for money flows; contradicts the modular monolith principle. |
| Hook-only plugin system (actions and filters everywhere) | Easy to start, but untyped, hard to test, and encourages modules to reach into each other's data. Typed extension points plus events give the same flexibility with guarantees. |
| Running third-party code in a separate process or WebAssembly sandbox | Strong isolation but impossible on shared hosting and heavy for the 2.0 timeline. Revisit for marketplace modules in SaaS if risk warrants it. |
| Separate database schema per module | Not portable across MySQL, MariaDB and PostgreSQL in the same way; complicates backups and transactions. Table ownership plus architecture tests achieve the boundary. |

## Consequences

Positive:
- Tenants see only what they use; editions map cleanly to modules.
- Module boundaries are testable and enforced in CI, which keeps the monolith maintainable and allows later extraction if ever needed.
- All migrations present means enabling a module is instant and safe; later releases add modules without changing 0.9 tables.
- Third-party modules have a documented, typed surface.

Negative and risks:
- Event-driven integration adds eventual consistency between modules (for example controlling projections lag by seconds). Financial flows re-read authoritative state through contracts inside their transaction.
- Running migrations for all installed modules means the schema contains tables of modules a tenant never uses; acceptable for storage, but update time grows with the module count.
- In-process third-party code can bypass capability checks by calling PHP functions directly; mitigated by review, signatures, SaaS ban on unsigned modules and static scanning in the marketplace pipeline, but not eliminated.
- Null implementations must be maintained for every contract.

## Open questions

- Exact signature format for module archives (detached signature over checksum list versus signed archive) to be aligned with the release signing approach for ZIPs and images.
- Whether marketplace modules in SaaS need process isolation from day one of 2.0, or whether review plus signature is sufficient.
- Versioning policy for extension point interfaces (semantic versioning per extension point versus tied to core minor versions).
- Whether tenants can install third-party modules themselves in multi-tenant SaaS, or only instance operators.
