# Backlog

This file holds ideas and requests that are outside the current milestone. Nothing here is committed scope. An item moves into a release only when the product owner accepts it and writes a story for it.

Rules:

- One row per idea. IDs are stable (`BL-001`, `BL-002`, ...).
- "Source" names where the idea came from (customer, internal use, review, support).
- Dates use DD.MM.YYYY.
- "Target release candidate" is a proposal, not a commitment.

| ID | Idea | Source | Date | Target release candidate |
|---|---|---|---|---|
| BL-001 | Audit checkpoints: daily head hash per chain signed with the instance Ed25519 key, exportable off-system, checked by `projektrack:audit:verify` (security baseline 8.2). Needs the instance key from licensing. | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-002 | Instance audit chain for operator events (no tenant). | Internal review (S-0907) | 18.11.2026 | 0.9.2 |
| BL-003 | Database guard against UPDATE and DELETE on audit tables: restricted database user on P2 to P5, triggers on P1 (security baseline 8.2). | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-004 | Admin > Security: run the audit verification on demand and browse or export the audit log. | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-005 | Admin > Users: lift a sign-in lock from the screen (today `projektrack:auth:unlock`), audited. | Internal review (S-0903) | 19.11.2026 | 0.9.1 |
| BL-006 | Settings > Security: list each session with device and last activity and sign out one at a time. | Internal review (S-0903) | 19.11.2026 | 0.9.2 |
| BL-007 | Larger bundled list of common passwords (open-licensed source, 12 characters and more). | Internal review (S-0903) | 19.11.2026 | 0.9.1 |
| BL-008 | Soft lockout: a device with an earlier successful sign-in (signed device cookie) is not locked by failures from elsewhere, so attackers cannot lock owners out (ASVS 2.2.1). | Security review (S-0903) | 19.11.2026 | 0.9.1 |
| BL-009 | Encrypted cast with XChaCha20-Poly1305 (libsodium), key IDs and `projektrack key:rotate` as in the security baseline section 4; today Laravel's encrypter with `APP_KEY` and `APP_PREVIOUS_KEYS`. | Implementation (S-0909) | 19.11.2026 | 0.9.1 |
| BL-010 | Malware scan of uploads (ClamAV when available) with quarantine; today `scan_status` = skipped. | Implementation (S-0909) | 19.11.2026 | 0.9.2 |
| BL-011 | Delete stored file content with its record and with the tenant; clean orphaned blobs. | Security review (S-0909) | 19.11.2026 | 0.9.1 |
| BL-012 | API tokens: daily pruning of expired tokens inside each tenant, `last_used_at` written at most once a minute, optional audit of token use. | Security review (S-0913) | 19.11.2026 | 0.9.1 |
| BL-013 | System check: once tenant domains can be edited in the UI, check only verified hosts and refuse private, loopback and link-local addresses on the address it builds. | Security review (S-0988) | 23.11.2026 | 0.9.1 |
| BL-014 | Shared links: token in the URL fragment (sent to the server by the page, never in request paths), so web server and proxy access logs cannot hold it; until then sample server configs exclude /s/ from access logs. Needs a product decision. | Security review (S-0987) | 23.11.2026 | 0.9.1 |
| BL-015 | Retention of the shared link access log (default 12 months, per tenant privacy setting) and pruning of long-expired links. | Security review (S-0987) | 23.11.2026 | 0.9.1 |
| BL-016 | Revoke a user's shared links when the user is deactivated or loses the right to share, or check the creator again on each use. | Security review (S-0987) | 23.11.2026 | 0.9.1 |
| BL-017 | Docker backups: off-site copy built into the backup service (S3-compatible target in the same jurisdiction) and retention of 14 daily plus 8 weekly; today 14 days on the host, off-site copy by the operator. | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-018 | Docker images: base images pinned by digest, `npm ci --ignore-scripts` where the build allows it, image scan in CI (with S-0924). | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-019 | Docker stack: CPU, memory and process limits per service; database and cache without extra capabilities and with read-only root file systems. | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-020 | Docker stack: IPv6 network so IPv6 visitors keep their own address; system check warns when the client address is the network gateway. | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-021 | Backup and restore write audit entries; the CI crawl also runs against the Caddy stack. | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-023 | CI job that lints the workflows (actions pinned by SHA, least-privilege permissions, no template injection), as security baseline 9 asks. | Security review (S-0924) | 23.11.2026 | 0.9.1 |
| BL-022 | Caddy: the 421 for a Host header that differs from the TLS server name comes before the site routes, so it may lack the mandatory headers; check and, if so, answer it from a route with the headers. Browsers never send such requests. | Security review (S-0923) | 23.11.2026 | 0.9.1 |
| BL-024 | Theme: check a tenant color against both modes when it is saved (brand.md 4.6), for example as a button boundary; background behind a tenant logo on the navy sidebar. | UX review (S-0910) | 23.11.2026 | 0.9.1 |
| BL-025 | Theme: contrast tests for hover and transparent states and for Arabic, third-party request check on every page in both modes, description for the empty notification list, font of Arabic fragments inside other languages, upright text instead of synthetic italics in Arabic. | UX review (S-0910) | 23.11.2026 | 0.9.1 |
| BL-026 | Settings in right to left languages: the gap between navigation and content is on the wrong side (`rtl:space-x-reverse` in `layouts/settings/Layout.vue`). | Screenshot review (S-0910), older than it | 23.11.2026 | 0.9.1 |
| BL-027 | Docker: PHP extension pcntl in the image, so the queue worker finishes its job on stop; `install` verifies the image signature (with S-0929). | Security review (S-0927) | 23.11.2026 | 0.9.1 |
| BL-028 | Licensing: threat model, CI check that files outside `ee` never reference `Ee\`, `ext-sodium` in composer.json, a test with two installs at the same time. | Security review (S-0974) | 23.11.2026 | 0.9.1 |
| BL-029 | License: do not count deactivated users once they exist; names of add-ons and features, banner outside the license screen and support link for an invalid license (with S-0977 and S-0979). | Security review (S-0976) | 23.11.2026 | 0.9.1 |
| BL-030 | Plain hosting: system check for the HTTPS redirect and for a proxy without `TRUSTED_PROXIES`; log files readable by their owner only; lock, downgrade guard and enforced backup in `projektrack:install`; TLS to the database; test images pinned by digest. | Security review (S-0925) | 23.11.2026 | 0.9.1 |
| BL-031 | Invoices: tax override, down payments, partial credit notes, DOCX, warning when tax country and billing address differ or held time was corrected, nightly gap and hash check, configurable number prefix and start, archive of the issued PDF, invoice from a service report. | Finance review (S-0961) | 24.11.2026 | 0.9.3 |
| BL-032 | Time: rounding and billable flag frozen at approval, minimum per cell, lock between month close and approval, timer (S-0946). Rates: cost rates, roles, currency conversion (S-0951), field permissions for money. | Finance and security review | 24.11.2026 | 0.9.3 |
| BL-033 | Modules: threat models, requests with foreign ids over HTTP for every module, module texts only after sign-in, user type refresh inside the transaction, deactivation of users. | Security review | 24.11.2026 | 0.9.3 |
| BL-034 | For the accountant: wording of all legal notices, AE or O for BA export, small business with EU customers, retention, place of issue and fiscal number in BiH, KM equivalent on tax-free EUR invoices. | Finance review | 24.11.2026 | 0.9.3 |
| BL-035 | Timer: correct the end of a forgotten timer. Tax corrections with old and new values in the audit. Document language of a draft follows the customer. Issue compares a hash of everything seen, not only the total. PDF/A producer name, veraPDF and KoSIT in CI. Age limit of exchange rates (31 days, to be confirmed). Mail header link per tenant host. | Second finance and security review | 24.11.2026 | 0.9.3 |
