# ADR-0009: AI provider layer

## Status

Accepted, 16.11.2026

## Context

Projektrack is AI native: AI capabilities are designed into every module through one shared layer instead of being added later. At the same time AI is **optional and off by default** (confirmed decision C-04): no data reaches any AI provider until a tenant admin explicitly enables it, and the product remains fully usable without AI. Further requirements:

- Provider-agnostic: several cloud providers and self-hosted models; the tenant chooses provider and model per use case and can switch without losing rules, prompts or history.
- The tenant's own API keys, stored encrypted; usage limits and cost caps per tenant, module and user; usage report for admins.
- Data control: per module the admin decides which data categories may be sent; field-level permissions apply to AI exactly as to people; optional redaction of personal data before sending.
- Every AI call is audited: who triggered it, which data categories, which provider and model, result, approval.
- Prompts and AI templates are translatable and customizable per tenant; output in the user's or the customer's document language.
- Human approval: customer replies, quotes, invoices, time bookings and changes with financial impact always need a person's approval before they are sent or saved. Low-risk internal actions (classify, tag, summarize, route) may run automatically only when an admin allows it per rule.
- Tests never call real providers; a fake provider is used in CI.
- Every AI feature has a working non-AI path; Community on plain AMP works fully without any provider.
- Release placement: provider layer, automation AI actions, proposal drafts and time assist in 1.0; service desk and finance assists in 1.1; controlling in 1.2; knowledge in 1.3; multi-step agents in 2.0. The 0.9 data model must need no breaking change when AI arrives.

## Decision

1. **One gateway in the core.** All AI use goes through the `AiGateway` contract. Modules never talk to providers directly; the outbound HTTP client blocks provider calls that do not originate from the gateway. A module declares its use cases and data categories in `module.json` (`ai.use_cases`, `ai.data_categories`).

2. **Off by default, hard switch.** Without an enabled `ai_tenant_settings` row, the gateway refuses every call before any data is assembled, and AI entry points are hidden in the UI. Enabling requires the `ai.settings.manage` permission, re-authentication, at least one configured provider and acceptance of a short data processing notice; the event is audited. Disabling takes effect immediately, cancels queued AI jobs and keeps history.

3. **Provider adapters.** An adapter interface covers text generation (chat-style messages), structured output (JSON with schema), embeddings and, where supported, document and image input. Adapters exist for generic cloud provider APIs and for self-hosted model servers with compatible HTTP APIs. Adapters are registered through the `ai.providers` extension point, so new providers are modules. Product code and documentation describe providers generically; vendor-specific details live only inside adapters. Each adapter reports capabilities (structured output, context size, tools, data region) so the gateway can reject unsuitable model choices.

4. **Tenant-owned credentials.** Tenants configure providers in `ai_providers` with their own API keys or endpoints. Keys are encrypted with application-level envelope encryption (key outside the database), never returned by the API or UI after saving (only a fingerprint), rotatable, and their use is audited. Self-hosted endpoints pass the SSRF guard with an admin-maintained allowlist for internal addresses. PROJEKTRA does not proxy or resell model access in 1.0.

5. **Request pipeline** for every call:
   1. Check tenant switch, use case enabled, edition entitlement, and the caller's `ai.use` permission.
   2. Check and reserve usage limits (requests, tokens, cost) for tenant, module and user; hard stop or warning per limit.
   3. Assemble context through the module's context builder, which loads data **as the requesting user** (or rule identity), so policies and field permissions apply; hidden fields are never loaded.
   4. Filter by `ai_policies`: data categories not allowed for this module are dropped; if a mandatory category is not allowed, the call is refused with a clear message.
   5. Optional PII redaction: names, emails, phone numbers, addresses, IBANs and IDs are replaced with stable placeholders before sending and restored in the output where appropriate.
   6. Render the prompt from the tenant's or system's prompt template in the target language.
   7. Call the provider through the adapter with timeouts, retries and circuit breaker.
   8. Validate structured output against the use case schema; invalid output is rejected, never partially applied.
   9. Record `ai_requests` (metadata, hashes, tokens, cost, status) and an `audit_log` entry; optionally store encrypted payloads with a short retention (off by default).
   10. Route the output: to an approval (`ai_approvals`) when the use case is customer-facing or financial, or apply directly only when the use case is low-risk and auto-run is allowed.

6. **Risk classes and approval.** Each use case has a fixed risk class in its catalog entry: `internal_low` (classify, tag, summarize, route), `internal_content` (drafts that stay internal), `customer_facing` (replies, proposals, portal answers), `financial` (quote lines, invoice data, time bookings, payment matching, dunning texts). `customer_facing` and `financial` outputs always create an `ai_approvals` record and stay drafts until a person with the matching business permission approves, edits or rejects them; this cannot be disabled by settings. `internal_low` may auto-run per rule when the admin sets `allow_ai_auto_run`.

7. **Usage limits and cost.** `ai_usage_limits` per tenant, module and user (day and month), with token and cost caps in the tenant's currency computed from the model price table. Counters are reserved before the call and corrected afterwards. Admins see a usage report per provider, model, module and user.

8. **Prompts as translatable content.** System prompt templates are shipped per use case and locale; tenants can override them (`ai_prompt_templates`, versioned). Output language is the user's language for internal content and the customer's document language for customer-facing content.

9. **Non-AI path everywhere.** Each use case names its non-AI alternative (for example: manual quote lines from the price list, rule-based ticket routing, manual payment matching with suggestions by amount and reference). Features are designed and tested first without AI; AI adds a suggestion layer on top.

10. **Testing.** A deterministic fake provider adapter (scripted responses, recorded fixtures, simulated latency, errors and token counts) is the only provider available in CI. A test asserts that no real provider adapter can be instantiated in the test environment. Contract tests per adapter run manually or in a separate, opt-in pipeline with test keys.

11. **Data model in 0.9.** The AI tables (section 21 of [data-model.md](../architecture/data-model.md)) are created in 0.9 but stay empty; time entries already carry `source` (including `ai_suggestion`), and automation, approvals and audit structures already accommodate AI metadata. No 0.9 table changes when AI ships.

12. **Release placement.**

| Release | AI scope |
|---|---|
| 0.9 | No AI features. Data model and gateway contract defined; AI tables empty. |
| 1.0 | Provider layer, admin settings, policies, limits, audit, fake provider; AI actions in automation rules (classify, summarize, extract, draft, translate); proposal drafts in quotes; time assist (description improvement, booking suggestions). |
| 1.1 | Service desk triage, categorization, routing, reply drafts, automatic answers from the knowledge base for known request types (approval rules apply); finance assists: incoming invoice and receipt extraction and coding, payment matching, dunning texts. |
| 1.2 | Controlling: budget and margin risk alerts, forecast explanations, plain-language questions turned into PQL and reports (shown as PQL for review before running). |
| 1.3 | Knowledge: wiki summaries, search answers with linked sources (only from pages the user can read). |
| 2.0 | Agents for multi-step tasks across modules through the API and MCP server, within the user's permissions, with a plan shown for approval and each financial or customer-facing step approved. |

Editions: the provider layer and basic assists are Community; AI actions in automation, proposal drafts, service desk, finance and controlling assists are Business.

## Alternatives considered

| Alternative | Why not chosen |
|---|---|
| Single built-in provider | Simpler, but creates vendor lock-in, conflicts with data sovereignty for self-hosted customers and with tenants that mandate their own provider. |
| PROJEKTRA-hosted AI proxy with bundled credits | Easier onboarding, but routes customer data through PROJEKTRA, adds cost and liability, and conflicts with "no external calls by default". May be offered later as an optional provider for SaaS. |
| AI on by default with opt-out | Faster adoption, but violates C-04, GDPR expectations and customer trust. |
| Each module integrating AI itself | Inconsistent policies, audit gaps, duplicated adapters. |
| Generic third-party LLM orchestration framework | Adds a large dependency surface for limited benefit; our needs (policies, audit, approvals, limits) are product-specific. Thin adapters are sufficient. |
| Storing full prompts and outputs always | Helpful for debugging, but increases personal data exposure; hashes plus opt-in short retention balance audit and privacy. |

## Consequences

Positive:
- Nothing leaves the system without an explicit admin decision; every call is traceable.
- Tenants keep control of providers, keys, costs and data categories.
- Uniform safety rules (permissions, approvals, limits) for all modules and for automation.
- CI is deterministic and free of provider costs.
- The 0.9 schema is ready; AI features are additive.

Negative and risks:
- Quality of AI features depends on the tenant's chosen model; the product must handle weak or slow models gracefully.
- Redaction is heuristic and can miss personal data in free text; the admin UI states this clearly and policies remain the primary control.
- Cost caps depend on maintained model price tables; prices are data and can be wrong until updated.
- Self-hosted model endpoints inside private networks require careful SSRF allowlisting.
- Approval steps add friction; UX must make approve, edit and reject fast.

## Open questions

- Default data categories per module and which are recommended for first enablement.
- Whether embeddings for knowledge search (1.3) are stored in the database (portable but large) or only in the search engine when available.
- Whether SaaS offers a PROJEKTRA-operated EU-hosted model as an optional provider, and how that fits data residency per region.
- Legal wording of the in-product data processing notice shown when enabling AI (to be reviewed with legal counsel).
- Handling of provider-side data retention settings: documented per adapter, or enforced through required adapter capabilities.
