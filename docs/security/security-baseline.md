# Security baseline

Status: Phase 0 baseline, 16.11.2026. Owner: security-engineer. Applies to every edition, module and infrastructure profile (see `docs/architecture/infrastructure-profiles.md`).

Principles: secure by design and by default; deny by default; no control depends on an admin remembering to switch it on; every control in this document has an automated check or a named manual review gate. A control without enforcement is a wish, not a control.

---

## 1. Scope and level

- Standard: **OWASP ASVS 5.0**. Chapter names below follow ASVS 5.0; a mapping to ASVS 4.0.3 will be kept in `docs/security/asvs-mapping.md` (open item, created in sprint L1) for customer questionnaires.
- **Level 2** for the whole product.
- **Level 3** for: authentication, session management, authorization (including tenant isolation and field-level permissions), and all finance functions (rates, costs, quotes, invoices, e-invoices, payments, licensing).
- Alignment: ISO/IEC 27001 Annex A and NIS2 logging expectations (operators can build their ISMS on Projektrack), SOC 2 readiness (audit logs, access reviews, change management, backups).

### 1.1 Control map

Legend for enforcement: **Pest** = feature or architecture test in `tests/Security/`, **Arch** = Pest architecture test or PHPStan custom rule, **Semgrep** = rule in `.semgrep/`, **ZAP** = DAST job, **Crawl** = no-index crawl test, **Gate** = mandatory review step in the pull request template.

| Area | ASVS 5.0 chapter | Level | Control | Enforced by |
|---|---|---|---|---|
| Password storage | V6 Authentication | L3 | Argon2id with tuned parameters, no other hash accepted for new hashes, rehash on login | Pest `PasswordHashingTest` asserts `$argon2id$` prefix and parameters |
| Multi-factor | V6 | L3 | TOTP and passkeys; 2FA enforced for admin and finance roles by default | Pest: admin without 2FA is redirected to enrollment on every route |
| Brute force | V6 | L3 | Rate limit and progressive lockout per account and per IP | Pest: 6th failed attempt blocked; lockout logged in audit |
| Re-authentication | V6 | L3 | Sensitive actions require recent auth (15 min) | Pest: route list scan; every route tagged `sensitive` has `password.confirm` or WebAuthn step-up middleware |
| Session | V7 Session Management | L3 | Server-side sessions, rotation on login and privilege change, idle and absolute timeout, `Secure`, `HttpOnly`, `SameSite=Lax` cookies with `__Host-` prefix | Pest `SessionSecurityTest`; ZAP cookie rules |
| Deny by default | V8 Authorization | L3 | Every route and API endpoint has a policy or explicit `public` marker | Arch: route list test fails on any route without `can:` middleware, policy call or `public` attribute |
| Tenant isolation | V8 | L3 | Central tenant global scope, `tenant_id` on every tenant-owned table | Arch: every model in `app/` and `modules/` uses `BelongsToTenant` or is listed as global; Pest cross-tenant suite |
| Field-level permissions | V8 | L3 | Rates, costs, margins, salaries hidden unless granted, in UI, API, exports and AI context | Pest: resource serialization tests per role; snapshot of API schema per role |
| Finance integrity | V2 Validation and Business Logic | L3 | Integer money, immutable posted invoices, gapless numbering, cancellation instead of deletion | Pest finance suite; Arch: no `float` in money namespaces; Semgrep rule bans `delete()` on finance models |
| Input validation | V2 | L2 | Form requests for every write, server-side only trust | Arch: controllers in write routes type-hint a `FormRequest` |
| Output encoding | V1 Encoding and Sanitization | L2 | Vue auto-escaping, `v-html` only through sanitizer component, Blade `{{ }}` only | Semgrep bans `v-html` outside `SafeHtml.vue` and `{!! !!}` outside allowlist |
| Injection | V1 | L2 | Query builder and bindings only, no raw SQL outside adapter layer | Semgrep rule on `DB::raw`, `whereRaw` outside `app/Support/Database/Adapters` |
| Frontend security | V3 Web Frontend Security | L2 | CSP with nonces, no inline scripts, security headers, `rel="noopener noreferrer"` | Crawl + Pest header test; ESLint rule on external links |
| CSRF | V3 | L2 | Laravel CSRF on all state-changing web routes, `SameSite` cookies | Pest: POST without token returns 419 for every web write route |
| API | V4 API and Web Service | L2 | Token scopes, rate limits per token and app, OpenAPI schema validation | Pest per endpoint; schema conformance test |
| Uploads | V5 File Handling | L2 | Type by content, size limits, random names, storage outside web root, no execution, optional malware scan | Pest upload suite (polyglot, double extension, SVG with script, oversized) |
| Tokens | V9 Self-contained Tokens | L2 | License tokens Ed25519, signed URLs with expiry, no JWT `alg=none` | Pest |
| OAuth/OIDC | V10 OAuth and OIDC | L2 | PKCE, exact redirect URI match, state and nonce checks | Pest with fake IdP |
| Cryptography | V11 Cryptography | L2 | libsodium primitives, keys outside DB, rotation | Arch: no `md5`/`sha1` for security, no `mt_rand`/`rand` (Semgrep) |
| Transport | V12 Secure Communication | L2 | TLS only, HSTS, TLS 1.2+ | Crawl (HSTS), `doctor` |
| Configuration | V13 Configuration | L2 | Debug off in production, no default secrets, no fingerprint | Pest: boot refuses `APP_DEBUG=true` with `APP_ENV=production`; Crawl |
| Data protection | V14 Data Protection | L2 | Field encryption, no sensitive data in logs or URLs, cache headers | Pest: log scrubbing test; Semgrep on logging of request bodies |
| Secure architecture | V15 Secure Coding and Architecture | L2 | Module boundaries, dependency hygiene, SBOM | Arch: modules do not access other modules' tables; supply chain jobs |
| Logging | V16 Security Logging and Error Handling | L2 | Audit log with hash chain, no stack traces to users | Pest `AuditChainTest`; Crawl checks error pages |
| Invisibility | Section 5 of this document | L2 | No indexing, no URL leaks, minimal fingerprint | Crawl test, `projektrack doctor` |

Release gate: a release cannot be tagged unless all automated checks above are green and the independent security review for the milestone is signed off (`Gate`).

---

## 2. Authentication

| Control | Default | Notes |
|---|---|---|
| Password hashing | Argon2id, memory 64 MiB, time cost 4, parallelism 1 (tuned per profile so a hash takes 250 to 500 ms) | Rehash on login when parameters change. Shared hosting with low memory: minimum 19 MiB, time cost 2 (OWASP minimum), reported by `doctor`. |
| Password policy | Minimum 12 characters, maximum 128, no composition rules, blocklist of common passwords bundled locally | Optional breached-password check via k-anonymity range query (off by default, because it is an external call). |
| Passkeys / WebAuthn | Available to all users; can replace password | User verification required for admin and finance roles. |
| TOTP | RFC 6238, 30 s, 6 digits, ±1 step tolerance, replay prevention per code | Secret stored encrypted (section 4). |
| Recovery codes | 10 single-use codes, 80 bits each, stored hashed | Regeneration requires re-auth. |
| Enforced 2FA | On for roles with `admin.*`, `finance.*`, `licensing.*`, `timesheets.view_rates`; configurable per role (can be extended, not disabled for admin roles) | Enrollment forced on next login; grace period 0. |
| Lockout and rate limiting | 5 failed attempts per account in 15 min -> 15 min lock, doubling to 24 h; 20 per IP per minute | Lockout never reveals whether the account exists. Admin can unlock; event audited. |
| Account enumeration | Identical responses and timing for unknown users on login, reset and invitation | Pest timing-insensitive response comparison. |
| Password reset | Single-use token, 256 bits, stored hashed, valid 60 min, invalidates sessions | Email contains no tracking. |
| Re-authentication | Within 15 min for: changing password, 2FA or email, managing roles and permissions, API tokens, license changes, exporting personal data, changing bank or tax details, posting or cancelling invoices, deleting tenants, key rotation | Passkey step-up preferred. |
| SSO | OIDC (Keycloak-compatible) and SAML (Enterprise); SCIM provisioning (Enterprise) | Local break-glass admin remains with enforced 2FA. |
| API tokens | Personal access tokens with scopes, expiry (default 90 days), shown once, stored hashed | Prefix `ptk_` for secret scanning. |

## 3. Authorization

- **Deny by default**: no route, API endpoint, job, export, automation action, MCP tool or AI action executes without an explicit permission check. Public routes (login, shared link resolver, health) carry an explicit `public` marker that the architecture test allowlists.
- **Policies on every action**: Laravel policies per model; controllers call `authorize()`; jobs and automation actions run as the triggering user or a dedicated service actor whose permissions are checked again at execution time.
- **Field-level permissions**: sensitive fields (`rate`, `cost`, `margin`, `salary`, `internal_cost_rate`, bank details) are declared in each module's `module.json` permission list and in a field registry. API resources, Inertia props, exports (PDF, DOCX, XLSX, CSV), search indexes, notifications and AI context builders use the same `FieldGate`. Hidden fields are omitted, not blanked.
- **Tenant isolation**: a global scope adds `tenant_id` to every query on tenant-owned models; `tenant_id` is set by the model on create and immutable; raw queries are only allowed in adapters that take the tenant as a mandatory parameter. Search indexes, cache keys, queue payloads, file paths and broadcast channels are namespaced by tenant.
- **Cross-tenant tests**: the suite seeds tenants A, B and C with identical structures; for every route and API endpoint the test logs in as a user of A and requests B's resources by ID, by key, via search, via export, via shared link and via webhook replay. Every attempt must return 404 (not 403, to avoid confirming existence). New routes are covered automatically because the test enumerates the route list.
- **Admin separation**: instance admin (operator) and tenant admin are different roles; the instance admin cannot read tenant business data without a logged, time-limited support access grant approved by a tenant admin.
- **Access reviews**: Admin > Security > Access review lists users, roles and last login for quarterly review (SOC 2 readiness).

## 4. Data protection

- **In transit**: TLS only; HTTP redirects to HTTPS; HSTS `max-age=31536000`; TLS 1.2 minimum; outbound connections verify certificates (no `verify=false`, Semgrep rule).
- **At rest, application level**: secrets and sensitive fields are encrypted with XChaCha20-Poly1305 (libsodium) through an `Encrypted` cast with versioned key IDs. Encrypted fields: TOTP secrets, OAuth client secrets, SMTP and integration passwords, webhook signing secrets, AI provider API keys, payment provider keys, bank account numbers of employees, salary data, license signing material references.
- **Keys outside the database**: `APP_KEY` and the data encryption key ring live in environment variables or a key file outside the web root (mode 0600), or in a KMS/HSM on P3 to P5. The license signing key and release signing keys never exist on customer instances.
- **Rotation**: `projektrack key:rotate` adds a new key ID, re-encrypts records in background batches, and retires the old key only after verification. Rotation is audited. Documented annual rotation for PROJEKTRA-operated profiles and after any suspected compromise.
- **Backups** are encrypted with a separate backup key (see infrastructure profiles).
- **Logs** never contain passwords, tokens, shared link tokens, full card or bank numbers, or request bodies of authentication routes. A log processor scrubs known patterns; Pest asserts scrubbing.
- **Caching**: authenticated responses send `Cache-Control: no-store`.

## 5. Web security

- **CSP with nonces**: per-response 128-bit nonce, `script-src 'nonce-...' 'strict-dynamic'`, no `unsafe-inline`, no `unsafe-eval`, `object-src 'none'`, `base-uri 'none'`, `frame-ancestors 'none'`. Vite build emits nonce-aware tags. CSP violation reports go to a same-origin endpoint, rate limited, stored per tenant.
- **CSRF**: Laravel token on every state-changing web request; API uses bearer tokens, never cookies, for third-party clients; Inertia requests carry the XSRF header.
- **SSRF guard** for webhooks, URL fetches, calendar feeds, OIDC discovery, AI provider endpoints and any outbound HTTP: one `SafeHttpClient` that
  - allows only `https` (and `http` only for explicitly configured internal services in P2 to P5),
  - resolves DNS once and connects to the resolved IP (prevents DNS rebinding),
  - blocks loopback, link-local (including `169.254.169.254` metadata), private (RFC 1918, RFC 4193), CGNAT, multicast and reserved ranges unless the operator allowlists a specific internal host,
  - disables redirects or re-validates every redirect target,
  - limits response size (default 5 MB) and time (connect 5 s, total 15 s),
  - strips credentials from logs.
  Semgrep bans direct use of other HTTP clients outside `SafeHttpClient`.
- **Webhooks**: outgoing signed with HMAC-SHA256 over timestamp and body, receivers reject timestamps older than 5 minutes; incoming webhooks verified by signature and idempotency key.
- **Uploads**: content type detected from bytes (`fileinfo`) and matched against an allowlist per context; extension must match; size limits per context (default 25 MB); filenames replaced by random IDs; storage outside the web root or in a private bucket; SVG either rejected or sanitized and served with `Content-Security-Policy: default-src 'none'; sandbox`; images re-encoded to strip metadata; archives not extracted server-side; optional ClamAV scan with quarantine; downloads always `Content-Disposition: attachment` except images previewed inline with sandbox CSP.
- **Signed URLs**: downloads use signed URLs bound to the user session or shared link, expiring after 5 minutes by default; signature is HMAC with a dedicated key; parameters canonicalized before signing.
- **Clickjacking**: `frame-ancestors 'none'` plus `X-Frame-Options: DENY`; embeddable components (2.x ecosystem) are rendered through a server-side proxy, never framed.
- **Open redirects**: redirect targets must be relative paths or allowlisted hosts.

## 6. Invisible by default

This section turns the "invisible by default" requirement into testable controls. It applies to every instance URL (self-hosted, SaaS, dedicated, partner, staging, demo) and to everything the application serves or generates. The public product website and public documentation site are separate properties and are out of scope. **No setting can disable any control in this section.**

### 6.1 Controls

| ID | Control | Implementation | Test |
|---|---|---|---|
| INV-01 | `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate` on every response | Global middleware registered first and also in the exception handler; web server configs for static files | Crawl test; Pest `NoIndexHeaderTest` iterates the route list including error responses |
| INV-02 | `<meta name="robots" content="noindex, nofollow, noarchive, nosnippet">` in every HTML page | Root Blade layout and every standalone HTML (error pages, maintenance page, installer, emails viewed in browser) | Crawl parses HTML |
| INV-03 | `robots.txt` = `User-agent: *` / `Disallow: /` | Static file in `public/` plus application route; no other content | Crawl compares byte-exact body |
| INV-04 | No sitemap, `humans.txt`, OpenSearch, schema.org, Open Graph, Twitter cards, `generator` meta | Lint rule on layouts; routes for these paths return 404 | Crawl asserts absence of `og:`, `twitter:`, `application/ld+json`, `itemscope`, `generator` |
| INV-05 | `Referrer-Policy: no-referrer` globally; external links `rel="noopener noreferrer"` | Middleware; `ExternalLink.vue` component; ESLint rule bans raw `<a target="_blank">` without rel | Crawl checks header and every external anchor |
| INV-06 | No third-party requests | Self-hosted fonts, icons, scripts; CSP without external hosts | Crawl: headless browser records all requests, any host other than the instance host fails the build |
| INV-07 | No tracking in emails | Mail templates without pixels or redirect links | Pest renders every mail template and asserts no `<img>` to external hosts and no rewritten links |
| INV-08 | Error pages and API errors reveal nothing | Generic error views, JSON problem details without paths, hosts or traces | Crawl requests unknown URL, invalid method, malformed JSON, forced 500 (test route in CI only) |
| INV-09 | Minimal fingerprint | `Server` and `X-Powered-By` removed; no product name, version or framework in headers, HTML meta, asset names (content-hashed generic names) or pre-login titles; generic, configurable login title; framework cookies renamed (`__Host-sid`, `XSRF-TOKEN` kept generic) | Crawl searches anonymous responses for `Projektrack`, `PROJEKTRA`, `Laravel`, `Inertia`, `Vue`, `Vite`, version patterns (`\d+\.\d+\.\d+`) |
| INV-10 | No public version or health detail | `/health` returns plain `OK` only, IP-restrictable; version only after login for admins | Crawl asserts body equals `OK` and no other status endpoints answer anonymously |
| INV-11 | SaaS host discretion | Wildcard DNS and wildcard certificate; random tenant labels by default; unknown hosts return generic 404 | P4 CI host routing tests |
| INV-12 | Custom domain disclosure warning | Admin UI and docs explain CT logs and recommend wildcard or unobvious subdomain | UI test asserts warning shown when adding a custom domain |
| INV-13 | Non-production protection | Dev, staging and demo require basic auth or IP allowlist in front of the app by default (`APP_ENV` other than `production` refuses to serve without it unless running in CI) | Pest boot test |
| INV-14 | Optional extra shielding | IP allowlist, hidden login path, VPN or zero-trust proxy guide, mutual TLS (Enterprise), crawler and scanner user agent blocking | Pest per option |
| INV-15 | License check-in discretion | Sends only instance ID, version, active user counts and domain to the PROJEKTRA licensing service over TLS; domains treated as confidential customer data | Pest asserts payload schema; licensing service never exposes domains publicly |
| INV-16 | Shared links invisible | Same headers, no previews, tokens never in referrer or logs | See section 7 |

### 6.2 CI crawl test specification

- **Target**: a running instance built from the commit under test, in the AMP profile (Apache and Nginx variants) and the Docker profile (Caddy), with seeded synthetic data.
- **Sessions**: anonymous, employee, project lead, finance user, tenant admin, portal user, and shared-link visitor.
- **Seeds for crawling**: `/`, login, password reset, installer completion page, `robots.txt`, `/.well-known/security.txt`, one shared sign-off link, one shared roadmap link (when available), one PDF, DOCX and XLSX download, one image attachment, one hashed JS and CSS asset, one font, unknown URL, `/health`, an API endpoint with and without token, a redirect (HTTP to HTTPS and post-login), a 401, 403, 404, 405, 419, 422, 429 and a forced 500.
- **Crawl**: headless Chromium follows same-origin links up to depth 4 per session (excluding logout and destructive actions), records every request and response.
- **Fail the build if**:
  1. any response (including redirects, errors, assets, downloads, API) lacks `X-Robots-Tag` with all six directives;
  2. any response lacks `Referrer-Policy: no-referrer`, or carries `Server` with a version or any `X-Powered-By`;
  3. `robots.txt` differs from the two-line disallow-all body;
  4. any HTML lacks the robots meta tag or contains Open Graph, Twitter card, schema.org, `generator`, sitemap or OpenSearch references;
  5. any anonymous response contains the product name, company name, framework names or a version string;
  6. the browser issues any request to a host other than the instance host;
  7. any shared link token appears in a `Referer` header, an outbound request or the application log;
  8. any external anchor lacks `rel="noopener noreferrer"`;
  9. any error response contains a file path, host name, SQL fragment or stack trace.
- **Output**: JUnit report plus a list of offending URLs; artifact retained 30 days.

### 6.3 `projektrack doctor`

- CLI `projektrack doctor [--external URL]` and Admin > System > Doctor. Runs from the instance against its own public URL (or a given URL) as an external client.
- Checks: INV-01 to INV-10 on a page, an asset, a download, a 404 and `robots.txt`; TLS and HSTS; server signature; web root misconfiguration (`/.env`, `/storage/`, `/vendor/` must be 404); cron heartbeat; capability table; debug mode; file permissions; pending security advisories.
- Output: list of findings with severity and plain-language fix instructions; critical findings show a persistent banner to instance admins. Exit code non-zero on critical findings so operators can run it from their own monitoring.

## 7. Shared and public links

- Tokens: 256 bits from a CSPRNG (minimum 128 bits required), base64url, never sequential IDs or derived from record IDs.
- Storage: only `SHA-256(token)` is stored; lookup by hash; the plain token is shown once at creation.
- Expiry: default 30 days, configurable per link and capped by tenant policy; revocation at any time, effective immediately; revoking a parent object revokes its links.
- Optional password (Argon2id) and optional restriction to an email address with one-time code.
- Scope: a link grants access to exactly one object and the actions listed on it (view, sign off, download); no navigation into the rest of the instance.
- Access log: time, link ID (not token), IP truncated per privacy setting, user agent family, action; visible to the link owner.
- Rate limiting per link and per IP; 10 wrong passwords lock the link for 1 hour.
- Same no-index headers, no previews, `Referrer-Policy: no-referrer`, token never logged (log scrubbing test) and never placed in outbound URLs.

## 8. Audit log

### 8.1 Coverage

Logins (success, failure, lockout, 2FA changes), sessions revoked, permission and role changes, user lifecycle, settings changes, module enable/disable, financial records (create, post, cancel, send, payment), exports and downloads of personal or financial data, API token lifecycle, shared links (create, access, revoke), license changes, key rotation, support access grants, AI calls (section 11), backup and restore, updates.

### 8.2 Hash chain design

- One chain per tenant plus one instance chain for operator events.
- Entry fields: `id`, `tenant_id`, `seq` (gapless per chain), `occurred_at` (UTC, microseconds), `actor_type`, `actor_id`, `action`, `subject_type`, `subject_id`, `ip_hash`, `context` (JSON, sensitive values redacted), `prev_hash`, `hash`, `key_id`.
- `hash = HMAC-SHA256(chain_key[key_id], prev_hash || canonical_json(entry without hash))`; canonical JSON sorts keys, uses UTF-8 and fixed number formatting. The HMAC key lives outside the database, so an attacker with database write access cannot recompute a valid chain.
- Appending: a transaction locks the chain head row (`audit_chain_heads`), reads `seq` and `prev_hash`, inserts the entry, updates the head. Works identically on MySQL, MariaDB and PostgreSQL.
- Append-only: no update or delete code paths (Arch test); on P2 to P5 the runtime database user has only `INSERT` and `SELECT` on audit tables; on P1 (single database user) database triggers reject `UPDATE` and `DELETE`, and the hash chain detects any bypass.
- Checkpoints: daily, the head hash of each chain is signed with the instance Ed25519 key and stored in `audit_checkpoints`; operators can export checkpoints to external storage or receive them by email, giving an external anchor.
- Verification: `projektrack audit:verify [--tenant] [--from]` recomputes the chain and checks checkpoints; runs weekly as a scheduled job and on demand from Admin > Security; failures raise a critical alert.
- Retention: configurable per tenant (default 10 years for financial events, 2 years for others); archived segments keep their final checkpoint so verification remains possible.

## 9. Supply chain

| Control | Implementation | CI job / gate |
|---|---|---|
| Lock files | `composer.lock` and `package-lock.json` committed; installs use `composer install --no-dev` and `npm ci` | Job fails if lock file is out of sync |
| Dependency scanning | `composer audit` and `npm audit --audit-level=high` on every PR and nightly | Fails on high or critical without a documented, time-boxed exception in `security/exceptions.yml` |
| Dependency review | New dependencies require license check (allowlist compatible with AGPL-3.0 and proprietary editions) and review | Gate in PR template; license checker job |
| SAST | Semgrep with community PHP, JS/TS and Laravel rules plus project rules in `.semgrep/` | Blocks merge on findings |
| Static analysis | PHPStan/Larastan max level, vue-tsc | Blocks merge |
| Secret scanning | Repository secret scanning with push protection plus a CI secret scanner on every PR; token prefixes (`ptk_`) registered | Blocks merge |
| DAST | OWASP ZAP baseline scan on every PR against the CI instance; full active scan nightly | Baseline blocks merge on medium and above |
| Container scanning | Image vulnerability scan of every built image | Fails on fixable critical |
| SBOM | CycloneDX SBOM for Composer, npm and each container image, attached to every release | Release job fails without SBOM |
| Signed images | cosign signature and provenance attestation for every image on GHCR, verified by `install.sh` and `projektrack update` | Release job; update refuses unsigned images |
| Signed ZIPs | `SHA256SUMS` plus Ed25519 signature of the ZIP and checksum file (release key separate from license key), also cosign blob signature; the updater verifies with the public key shipped in the previous release | Release job; updater test with tampered ZIP must fail |
| Reproducibility | Builds from tagged commits on CI runners only; no manual uploads | Release workflow protected by environment approval |
| Branch protection | Required reviews, signed commits for release branches, status checks required | Repository settings, audited quarterly |
| GitHub Actions hygiene | Actions pinned by commit SHA, least-privilege `permissions:` per job | Semgrep or linter rule on workflows |

## 10. Privacy: GDPR and CCPA/CPRA tooling

- **Data subject requests**: Admin > Privacy > Requests handles access, export (JSON plus attachments in a ZIP), rectification, restriction, erasure and objection. CCPA/CPRA "know", "delete" and "correct" requests use the same tools.
- **Erasure respecting retention**: personal data is anonymized where legal retention applies (invoices, accounting records, approved times for GoBD: 10 years in Germany; country packs define retention), deleted elsewhere. The tool shows which records are retained and why.
- **Retention policies** per data category and tenant, executed by a scheduled job with an audit trail.
- **Records of processing** template and data processing agreement template for operators (`docs/admin/privacy.md`, open item for 0.9 hardening).
- **No telemetry** without explicit opt-in; no external calls by default.
- **Data minimization**: IP addresses truncated or hashed in audit entries per tenant setting.
- **Data residency**: SaaS tenants stay in their region including backups (P4).
- **Breach support**: incident tooling exports affected records and timeline for the 72-hour GDPR notification.

## 11. AI data flows

AI is optional and **off by default** (decision C-04, ADR-0009). With AI disabled, nothing is ever sent to any AI provider and every feature has a working non-AI path.

- **Enablement**: only a tenant admin can enable AI, per module and per use case; enabling requires re-auth and is audited.
- **Provider configuration**: provider-agnostic adapter (cloud providers or self-hosted models). Endpoints are configured by the admin and called only through `SafeHttpClient`. The product names no provider in code paths, UI defaults or docs examples.
- **Keys**: the tenant's own API keys, stored encrypted (section 4), never shown again after entry, never logged, never sent to the browser.
- **Data categories** (each module declares which categories a use case needs; the admin allows categories per module):

| Category | Examples | Default when AI is enabled |
|---|---|---|
| D0 Configuration | Status names, issue types, templates | Allowed |
| D1 Work content | Task titles, descriptions, comments, wiki text | Ask admin per module |
| D2 Personal data | Names, emails, phone numbers | Redacted (pseudonymized) unless allowed |
| D3 Financial and HR | Rates, costs, margins, salaries, bank details | Never sent unless explicitly allowed and the acting user has the field permission |
| D4 Secrets | Passwords, tokens, keys | Never sent, not configurable |

- **Field permissions respected**: the AI context builder uses the same `FieldGate` as the API with the permissions of the acting user (or the rule owner for automation), so AI never receives a field the user could not see.
- **Every call audited**: actor, use case, module, data categories sent, record references, provider ID and model ID (as configured by the tenant), token counts, cost, outcome, approval decision and approver. Prompts and responses are stored only if the tenant enables it, with retention limits.
- **Human approval**: customer-facing output and anything with financial impact (quotes, invoices, time bookings, replies to customers) is a draft until a person approves it.
- **Limits**: usage and cost caps per tenant, module and user; rate limits; prompt injection mitigations (content from records treated as data, tool calls restricted to the user's permissions, no autonomous outbound actions without approval).
- **Testing**: CI uses only the fake provider adapter; a test fails if any real provider host is contacted.

## 12. Synthetic data rule

- No real personal or customer data in the repository, tests, seeds, fixtures, screenshots, demo data, staging or documentation.
- Allowed: generated data using reserved domains (`example.com`, `example.org`, `*.example`, `*.test`), fictitious companies, IBANs and tax IDs from published test ranges or with invalid check digits marked as test data.
- Enforcement: a CI scanner checks seeds, fixtures and docs for email domains outside the reserved list, phone numbers outside test ranges, valid-looking IBANs not in the test list, and image metadata; Gate item in the PR template; staging is seeded only from the demo seeder.

## 13. Incident response and disclosure

- **`SECURITY.md`** in the repository: supported versions, how to report (dedicated security mailbox at the product domain, encrypted reporting option), what to include, response times, safe harbor for good-faith research, no bug bounty at launch.
- **`security.txt`**: PROJEKTRA publishes RFC 9116 `security.txt` on the product website and company website. Instances serve `/.well-known/security.txt` only with the operator's own contact when configured, without product name or version, with `Expires`, and with no-index headers; otherwise 404.
- **Response targets**: acknowledge within 3 business days, triage within 7 days, fix critical within 7 days and high within 30 days of triage, coordinated disclosure within 90 days.
- **Advisories**: published as repository security advisories with CVE IDs where applicable; shown in Admin > Updates on every instance with severity and affected versions; patch releases eligible for automatic updates.
- **Incident handling** (PROJEKTRA-operated profiles): severity classification, incident commander, evidence preservation (audit log export with checkpoints), customer notification, GDPR 72-hour notification support, NIS2 early warning within 24 hours and notification within 72 hours where PROJEKTRA or the customer is in scope, post-incident review within 10 business days.

## 14. Threat modeling

- One STRIDE threat model per module in `docs/security/threat-models/<module>.md`, created before the module is implemented and updated whenever its data flows, trust boundaries or permissions change.
- Template: `docs/security/threat-models/TEMPLATE.md`.
- Each threat gets an owner, a mitigation that links to a control in this document, and a test or review that verifies it. Open high risks block the milestone.
- Threat models are reviewed by security-engineer independently of the module's authors.
