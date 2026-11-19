# ADR-0006: Multi-tenancy, SaaS and white label

## Status

Accepted, 16.11.2026

## Context

Projektrack must serve several companies in one instance with strictly separated data, from a single-company AMP installation up to a multi-region SaaS. Requirements:

- Per tenant: branding, numbering, tax and bank details, default language, currency, time zone, modules, license plan.
- SaaS: subdomain per tenant (random by default, readable alias optional), custom domains with automatic TLS, shared database with tenant scope by default, database per tenant for Enterprise, data residency in an EU region (default) or a US region chosen at signup, data never leaving its region (backups included).
- Instance URLs must stay invisible: wildcard certificate and DNS so tenant names do not appear in Certificate Transparency logs, no indexing, no fingerprint.
- White label per tenant and per instance: product name, logo, colors (design tokens), login page, email sender, document templates, PWA manifest, help and legal links; "Powered by" removable where the edition allows; the brand appears in strings only through `{brand}`.
- Partners manage several white-labeled tenants or instances.
- The first customer license (1.12.2026) is a managed dedicated instance (C-03); SaaS self-signup arrives in 1.0 and the US region in 1.1.
- Must run on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16, and on plain AMP.

## Decision

1. **Shared schema with a `tenant_id` column** on every tenant-owned table (see [data-model.md](../architecture/data-model.md), section 1.1). One schema serves all deployment profiles.

2. **Isolation in depth**:
   - A central tenant context, set once per request, job, command or scheduled task; code cannot run tenant-owned queries without it.
   - A global query scope adds `tenant_id` to every tenant-owned model; creating rows fills `tenant_id` from context; changing `tenant_id` is forbidden.
   - **Tenant-safe composite foreign keys** `(tenant_id, parent_id)` referencing a unique `(tenant_id, id)` index make cross-tenant references impossible at the database level on all three engines.
   - Raw queries are allowed only in adapter classes that take the tenant ID explicitly; a static analysis rule flags query builder use outside models and adapters.
   - Cache keys, storage paths (`tenants/{tenant_id}/...`), search documents, queue payloads, rate limit keys, broadcast channels and signed URLs carry the tenant ID.
   - CI contains a dedicated isolation suite that creates two tenants and actively attempts cross-tenant reads and writes through every route, API endpoint, export, shared link and job type.

3. **Database per tenant (Enterprise and dedicated)**: the same schema (including `tenant_id`, holding one value) runs in a separate database. Tenant resolution switches the connection before any query. Moving a tenant from shared to dedicated is an export and import by `tenant_id`, made collision-free by ULID primary keys. Application-level isolation stays active in both modes.

4. **Tenant resolution by host**. `tenant_domains` maps hosts to tenants (cached). Kinds: `random` (default, for example `k7m2q9x4.<saas-domain>`), `alias` (readable name chosen by the customer), `custom` (customer domain). Unknown hosts get a generic, unbranded 404. API tokens and OAuth grants are bound to their tenant and rejected on any other host. Single-tenant installations have exactly one tenant and one or more domains.

5. **Users belong to one tenant.** E-mail addresses are unique per tenant, not per instance. A person working for two tenants has two accounts (optionally linked by SSO). Partner staff access customer tenants through the partner console with explicit, time-bound, audited delegated access, never through shared accounts. This keeps isolation simple and auditable.

6. **Regions and the control plane**. Each SaaS region is a separate deployment (application, database, storage, backups) in that region. A small **control plane** operated by PROJEKTRA holds only routing and operational metadata (tenant ID, region, plan, domains, health, rollout waves), never business data. Signup chooses the region; the tenant is provisioned only in that region. Moving regions is a support-assisted export and import with customer consent.

7. **TLS and domains**. SaaS subdomains use a wildcard certificate and wildcard DNS. Custom domains are verified by DNS record, then certificates are issued automatically by the reverse proxy using on-demand issuance gated by an "ask" endpoint that only approves verified `tenant_domains`. Documentation warns that custom-domain certificates appear in Certificate Transparency logs and recommends unobvious subdomains or wildcard certificates.

8. **White label as layered configuration**. `brand_profiles` (instance default with `tenant_id` NULL, tenant override) holds product name, logo, favicon, design tokens, email sender, legal and help links and `show_powered_by`. Resolution: tenant, then instance, then shipped PROJEKTRA brand. Design tokens map to CSS custom properties; templates, emails, PWA manifest and documents read the resolved brand. Strings never contain brand names, only `{brand}`. Removing "Powered by" and custom sender domains require an entitlement (Enterprise, or the white label add-on for Business). Partners manage brand profiles of their tenants through the partner console (2.0).

   Implementation note (S-0911, 19.11.2026): `App\Branding\Brand` resolves tenant profile, then `config/brand.php` (the instance layer for 0.9, instead of a NULL-tenant row), then the shipped name. Tenant profiles hold product name, logo, primary color and e-mail sender name; favicon, further tokens, legal and help links follow. `show_powered_by` stays on until licensing provides the entitlement. The sender display name follows the brand; the sender address stays the instance's.

9. **Email sending identity** per tenant: sender name and domain from the brand profile, with SPF, DKIM and DMARC verification status shown in admin before the domain is used; until verified, mail is sent from the instance domain with the tenant name as display name.

10. **Per-tenant configuration**: numbering sequences, country pack and tax profile, bank accounts, currency, locale, time zone, modules and settings are tenant rows; nothing tenant-specific lives in files.

11. **Release placement**: 0.9 ships the full tenancy model, host resolution, isolation suite, brand profiles and managed dedicated instances; 1.0 adds SaaS self-signup, random subdomains, custom domains with automatic TLS and the control plane; 1.1 adds the US region; database per tenant for Enterprise is available from 1.0; partner console in 2.0.

## Alternatives considered

| Alternative | Why not chosen |
|---|---|
| Schema per tenant | Native only on PostgreSQL; MySQL and MariaDB treat schemas as databases. Not portable, and migrations across thousands of schemas are slow. |
| Database per tenant for all tenants | Strongest isolation, but impractical on shared hosting, expensive for Cloud Free tenants and slow to migrate. Offered for Enterprise only. |
| Separate instance per customer only | Simple and isolated, but no Cloud Free or self-signup economics, and many small instances to update. Kept as the managed dedicated profile. |
| PostgreSQL row-level security as the main mechanism | Not available on MySQL or MariaDB. May be added as an optional extra layer on PostgreSQL deployments later. |
| Third-party tenancy package for the framework | Useful ideas, but its database-per-tenant focus and automatic bootstrapping conflict with our composite-key, single-schema and AMP constraints. We implement a small tenancy core and keep it fully tested. |
| Global user accounts across tenants | Convenient for consultants, but complicates isolation, permissions, data export and erasure. Revisit with SSO-based account linking. |

## Consequences

Positive:
- One schema and one code path for all deployment profiles; dedicated databases are an operational choice, not a code fork.
- Cross-tenant leakage requires both an application bug and a database constraint failure.
- Tenant export, deletion and region moves are straightforward because every row carries `tenant_id`.
- White label requires no code changes per customer.

Negative and risks:
- Every tenant-owned table carries an extra column and composite indexes, increasing index size.
- Composite foreign keys must be declared consistently in every migration; a migration lint rule enforces it.
- Noisy neighbors in the shared database need per-tenant rate limits, job fairness and query budgets.
- Users needing access to several tenants have several accounts until account linking exists.
- On-demand TLS for custom domains depends on the reverse proxy chosen for SaaS; AMP installations handle custom domains with their hosting panel.

## Open questions

- Final SaaS domain and wildcard DNS provider per region (placeholder `*.projektrack-cloud.example`).
- Whether partners get one partner tenant with delegated access to customer tenants or separate partner accounts in the control plane only.
- Data residency guarantees for email delivery and optional AI providers chosen by the tenant (tenant responsibility versus enforced region filters).
- Per-tenant encryption keys (envelope encryption with tenant data keys) for SaaS from 1.0 versus a single instance key in 0.9.
