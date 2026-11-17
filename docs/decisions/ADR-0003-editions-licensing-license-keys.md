# ADR-0003: Editions, licensing and license keys

- **Status:** Accepted, 16.11.2026
- **Deciders:** Product owner, solution architecture, security
- **Related:** ADR-0001 (stack), ADR-0002 (module system), ADR-0004 (invoicing of licenses), ADR-0006 (SaaS and white label)

## Context

Projektrack follows an open core model with per-user licensing, similar to Odoo. All intellectual property belongs to PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (licensor). Release 0.9 on 01.12.2026 must sell and deliver the first license (Founding Customer plan, Business edition) through a direct sale: order, invoice, bank transfer, activation. A self-service checkout follows in 1.0.

Constraints:

- PROJEKTRA is established in Bosnia and Herzegovina, where Stripe accounts are not available to local businesses. The design cannot depend on Stripe for PROJEKTRA's own license sales.
- Self-hosted instances may be air-gapped. License validation must work offline.
- Instances must stay invisible (no indexing, no URL leaks). The licensing service treats instance domains as confidential customer data.
- No telemetry without opt-in. The license check-in is a contractual function of commercial editions only and must be minimal.
- Licensing must never cause data loss and never lock users out of Community functions.

## Decision

### 1. Editions and code layout

| Edition | License | Code location | Distribution |
|---|---|---|---|
| Community | [AGPL-3.0-only](https://www.gnu.org/licenses/agpl-3.0.html) | `/app`, `/modules`, `/lang` | Public repository from 1.0; Community ZIP and image without `/ee` |
| Business | Proprietary (PROJEKTRA EULA), per user | `/ee/modules` | Full ZIP and image; features unlocked by a valid license |
| Enterprise | Proprietary, per user | `/ee/modules` | Same artifact, more features in the license |
| Partner | Proprietary, partner agreement | `/ee/modules` + partner console | Same artifact, partner flags in the license |

Rules:

- Core and Community modules never import code from `/ee`. Commercial modules use the same extension points as third-party modules (ADR-0002). A CI check fails if any file outside `/ee` references an `Ee\` namespace.
- Every `/ee` module declares `"edition": "business"` (or higher) in `module.json`. The module registry enables it only when the installed license grants that edition or a specific add-on.
- File headers: Community files carry the AGPL-3.0 notice; `/ee` files carry the proprietary notice with the PROJEKTRA copyright line.
- PROJEKTRA is the sole copyright holder of the core. To keep the right to license the core under commercial terms to Business and Enterprise customers (dual licensing), external contributions require a Contributor License Agreement from 1.0 (see Open questions).
- Before 1.0 the repository is private (confirmed decision C-02).

### 2. Per-user licensing model

| User type | Billed | Definition |
|---|---|---|
| Full user | Yes | Active internal user with any permission beyond the Light set |
| Light user | Yes, lower price | Active user limited to time, expenses, own tasks and approvals (permission set flagged `light` in the role editor) |
| Portal user | No | Customer contacts in the portal and on sign-off links |
| Deactivated user | No | Cannot log in; data kept |
| API and service accounts | No | Non-interactive, cannot log in to the UI |

- Counting: a user is "active" if enabled and not a portal or service account. The user type is derived from effective permissions, not from a free choice, so it cannot be gamed by labeling. Admins see the reason ("Full user because of permission invoices.finalize").
- Counts are per instance (self-hosted) or per tenant (SaaS). A multi-tenant self-hosted instance sums its tenants.

### 3. License token format

A license is a compact, signed token. It is human-transportable (license file, email, copy and paste) and validated offline with the public keys shipped in every release.

**Wire format**

```
PTKL1.<payload>.<signature>
```

- `PTKL1` is the format prefix and version (Projektrack license, format 1).
- `<payload>` is the base64url encoding (RFC 4648 section 5, no padding) of the canonical JSON payload.
- `<signature>` is the base64url encoding of the 64-byte Ed25519 signature ([RFC 8032](https://www.rfc-editor.org/rfc/rfc8032)).

**Canonical signing scheme**

1. Build the payload object.
2. Serialize it with the JSON Canonicalization Scheme, [RFC 8785](https://www.rfc-editor.org/rfc/rfc8785) (sorted keys, no insignificant whitespace, UTF-8, ECMAScript number formatting). Only integers, strings, booleans, arrays, objects and `null` are allowed; no floating-point numbers (prices are never part of the token).
3. `payload_b64 = base64url(canonical_json_bytes)`.
4. `signing_input = ASCII("PTKL1.") || ASCII(payload_b64)`.
5. `signature = Ed25519_sign(private_key[kid], signing_input)` using libsodium `sodium_crypto_sign_detached`.
6. Token = `"PTKL1." + payload_b64 + "." + base64url(signature)`.

Verification reverses steps 4 and 5 with the public key selected by `kid`, then decodes the payload and checks that re-canonicalizing it yields the identical bytes (rejects non-canonical payloads). Signing the encoded string rather than re-serialized JSON avoids any ambiguity between signer and verifier.

**Payload example (synthetic data)**

```json
{
  "addons": ["white_label"],
  "binding": {
    "domains": ["projects.customer-example.test"],
    "instance_id": "inst_01JD8Q7Z5M3K9V2X4B6N8P0R1T"
  },
  "check_in": { "interval_hours": 24, "required": false },
  "customer": { "id": "lc_000123", "name": "Example GmbH" },
  "edition": "business",
  "expires_at": "2027-12-01T00:00:00Z",
  "features": ["automation.full", "einvoice.inbound", "servicedesk"],
  "grace": { "expiry_days": 14, "over_seat_days": 30, "pending_payment_days": 14 },
  "issued_at": "2026-12-01T09:00:00Z",
  "iss": "projektra-licensing",
  "kid": "ptk-2026-01",
  "license_id": "lic_01JD8QA2H6W4C8E0G2J4L6N8Q0",
  "not_before": "2026-12-01T00:00:00Z",
  "plan": "founding_customer_business",
  "seats": { "full": 12, "light": 5 },
  "serial": 3,
  "status": "pending_payment",
  "status_until": "2026-12-15T23:59:59Z",
  "v": 1
}
```

Field rules:

- `kid` selects the public key. `serial` increments with every reissue of the same `license_id`; an instance never accepts a lower serial than the one it has stored (prevents rollback to an older, larger license).
- `binding.instance_id` is generated at install time (ULID, random) and stored in the database and in a file outside the web root. `binding.domains` lists the allowed base URLs. Domain mismatch triggers a warning and the over-limit grace rules, never a hard stop, because domains change during migrations.
- `status` is `active`, `pending_payment` or `trial`. `status_until` is the end of the pending-payment or trial window.
- `features` and `addons` are explicit; the edition implies a default feature set, and explicit entries add to it. Unknown features are ignored by older versions.
- Customer name is included because it appears on the license screen; no personal data (no names of people, no email addresses) is ever put into the token.

**Key storage and rotation**

- Private signing keys never enter the database or the repository. On the PROJEKTRA instance the key file lives outside the web root, readable only by the licensing worker user, encrypted at rest (libsodium secretbox) with a passphrase held in the environment of that worker. Migration to an HSM or cloud KMS with Ed25519 support is planned for 1.1.
- Every release ships a public key set `config/license-keys.php` with entries `{kid, public_key, valid_from, valid_until, status}`. A token is accepted only if its `issued_at` lies within the validity of its `kid`.
- Planned rotation: yearly. The next key is published in a release at least one minor version before it is used for signing. Old keys remain valid for verifying tokens they signed until those tokens expire.
- Compromise: the `kid` is marked `revoked` in a signed key-status document delivered via check-in and in the next release; affected licenses are reissued under a new `kid` and sent to customers. Offline customers receive a new license file by email.
- Signing is done by a single queued job; every issuance writes an audit record (license id, serial, kid, actor, reason) to the tamper-evident audit log.

### 4. Activation code

Format: `PTK-XXXX-XXXX-XXXX`, for example `PTK-7H4Q-M2XD-9KLA`.

- Alphabet: 32 characters `23456789ABCDEFGHJKLMNPQRSTUVWXYZ` (no `0`, `1`, `I`, `O` to avoid confusion when read aloud or typed). Input is case-insensitive and ignores spaces and hyphens; characters outside the alphabet are rejected with a helpful message.
- 11 random characters (55 bits, CSPRNG) plus 1 check character (weighted sum mod 32) to catch typos offline before any request.
- An activation code is not a license. It is a single-purpose secret that lets one instance download its signed license from the licensing service. It is stored only as a hash (SHA-256 with a server-side pepper), can be revoked and rotated in the license portal, and activation attempts are rate limited per IP and per code (5 per hour).

### 5. Activation, validation and check-in

**Online activation (default for self-hosted):** Admin > License > Activate > enter code. The instance sends `{activation_code, instance_id, base_url_host, product_version}` over TLS to the licensing service, which binds the license to the instance and returns the token. Under one minute end to end.

**Offline activation (air-gapped):** download the license file in the license portal or receive it by email, upload it in Admin > License. Validation is entirely local.

**Offline validation on every boot and hourly:**
1. Parse and verify signature with the shipped key set.
2. Check `not_before`, `expires_at`, `serial >= stored_serial`, `kid` status.
3. Check binding (instance id; domain as warning).
4. Compute effective state (see section 6).
5. Clock sanity: the instance stores the highest time it has ever seen. If the system clock is more than 48 hours behind that value, the admin sees a warning and the license screen shows "clock problem"; no lockout.

**Daily check-in (commercial editions with online activation only):**
- Sent: `instance_id`, `license_id`, `serial`, product version, active counts `{full, light, portal}`, base URL host. Nothing else: no tenant names, no user names, no emails, no business data, no IP addresses beyond what TLS needs.
- Received: newer token (after renewal, seat change, payment received), key status document, optional security advisory list.
- Retention on the PROJEKTRA side: last check-in per instance plus a daily count history for 13 months; then aggregated.
- Community edition makes no check-in and no outbound call at all. Admins of commercial instances can switch to offline mode, which stops check-ins; license updates then arrive as files.
- Failure handling: if check-in fails, nothing changes; the installed license works until its expiry plus grace.

### 6. Soft enforcement and grace periods

| Situation | Grace | During grace | After grace |
|---|---|---|---|
| Pending payment (channel 1) | 14 days from issuance (`status_until`) | Full functionality, banner for admins "Payment pending" | Commercial features read-only; banner with "Send payment confirmation" action. Community functions unaffected |
| More active users than licensed | 30 days from first exceed | Warning to admins, "Add users" button; all users keep working | New users of the exceeded type cannot be activated until true-up or reduction; existing users keep working. True-up invoiced pro rata |
| License expired | 14 days after `expires_at` | Banner, renewal reminders | Commercial features read-only (view and export, no create or edit); Community features fully usable |
| Check-in failing | none needed | Nothing changes | Nothing changes until expiry |
| Signature invalid or key revoked | none | Commercial features read-only immediately, clear error, support link | Same |

Principles: never delete or hide data, never block login, never block Community functions, never block exports of the customer's own data. Read-only means commercial screens remain viewable and exportable. Reminders for renewal go out 60, 30 and 7 days before expiry.

### 7. Sales channel 1 (0.9): direct B2B sale, invoice and bank transfer

1. Customer accepts the order form or quote via the online acceptance link.
2. The PROJEKTRA instance issues the invoice in EUR with the correct tax case (ADR-0004; for a German business customer: BiH export of services, place-of-supply notice, recipient liable for German VAT, wording confirmed by the accountant).
3. The license is issued immediately with `status = pending_payment`, `status_until = issuance + 14 days`.
4. Payment is marked received manually in 0.9 (bank statement import later). The licensing module reissues the token with `status = active` and a higher `serial`; online instances pick it up at the next check-in, offline customers receive the file.
5. The customer admin receives the activation email with the code and the license portal invitation.

The licensing module entities: license customer (linked to the CRM client), subscription (plan, edition, seat counts, add-ons, term, renewal date, price, price lock), license, activation (instance id, domain, last check-in), history of every change.

### 8. Sales channel 2 (1.0): merchant of record adapter

- A `MerchantOfRecordGateway` interface with operations: `createCheckout(plan, seats, term, customerRef)`, `verifyWebhook(request)`, `mapEvent(payload) -> LicensingCommand`, `customerPortalUrl(subscriptionRef)`, `changeSeats(subscriptionRef, seats)`.
- Webhooks are verified by signature, deduplicated by event id (idempotency table), processed in a queued job, and mapped to licensing commands (create subscription, change seats, renew, cancel, refund). The license is issued or reissued automatically.
- **Default: Paddle.** Alternatives behind the same interface: Polar, Lemon Squeezy, Freemius, FastSpring. Paddle lists Bosnia and Herzegovina as a buyer country ([Paddle supported countries](https://developer.paddle.com/concepts/sell/supported-countries-locales)); seller onboarding and payouts for a BiH company could not be confirmed from Paddle's public pages on 16.11.2026 (**verify with Paddle before 1.0 development starts**). Polar ([supported countries](https://polar.sh/docs/merchant-of-record/supported-countries)), Freemius ([supported countries](https://freemius.com/help/documentation/selling-with-freemius/supported-countries)) and Lemon Squeezy ([supported countries](https://docs.lemonsqueezy.com/help/getting-started/supported-countries)) publish seller country lists that must be checked for BiH at the same time.
- With a merchant of record, the MoR is the seller to the end customer; PROJEKTRA invoices only the MoR (one B2B invoice per payout period). Tax handling of that invoice: see ADR-0004 and Open questions.

### 9. Founding Customer plan

- Edition: Business. Price locked for 24 months from contract start (`price_lock_until` on the subscription), included onboarding package, direct roadmap influence (quarterly roadmap call).
- Configured as plan data `founding_customer_business` in the licensing service, not as code; prices come from the price list (Business full 14.90 EUR, light 4.90 EUR per user per month, yearly billing) unless the product owner sets otherwise.
- Availability window and maximum number of founding customers are plan attributes (see Open questions).
- Renewal after the lock period moves to the then-current Business price list without a promotional jump beyond the published list price.

### 10. Trial

The 30-day Business trial and self-service checkout are not in 0.9 (assumption A-11). The token format already supports `status = trial`.

## Alternatives considered

- **Third-party license server (Keygen, Cryptlex, LicenseSpring):** mature, but adds a mandatory external dependency and moves customer data (instance domains) to a third party, which conflicts with the invisibility rule. The licensing module is small enough to build in-house. Rejected.
- **JWT (JWS with EdDSA) instead of an own prefix:** technically close; our format is effectively a JWS compact serialization with a fixed header. We chose the fixed `PTKL1` prefix to prevent algorithm confusion (`alg` header attacks) and to make the token recognizable in support. Rejected JWT libraries, kept the same security properties.
- **RSA or ECDSA P-256 signatures:** larger keys or nonce-misuse risk; Ed25519 is deterministic, fast and available in PHP core via ext-sodium. Rejected.
- **Hard enforcement (lockout on expiry or over-seat):** conflicts with the "never lock out, never lose data" rule and damages trust. Rejected.
- **Business Source License or SSPL for the core instead of AGPL:** not OSI-approved; Odoo-style open core with AGPL Community is better for adoption and the WordPress/Moodle plugin ecosystem. Rejected.
- **Stripe Billing for license sales:** not available to BiH businesses. Rejected for PROJEKTRA's own sales (tenants may still use Stripe for their own customers).

## Consequences

**Positive**
- Licenses validate offline with only PHP core (ext-sodium); air-gapped customers are first-class.
- Channel 1 works in 0.9 with existing invoicing; channel 2 plugs in through one interface.
- Check-in is minimal and documented, which supports GDPR and the invisibility rule.

**Negative and risks**
- Soft enforcement relies on honesty and contracts; determined users can patch AGPL code. Accepted: the commercial value is in `/ee`, updates and support.
- A leaked private key forces reissue of all affected licenses; mitigated by key isolation, rotation and revocation.
- Domain binding as warning only allows reuse of a license on a second instance for a time; the licensing service flags duplicate `license_id` check-ins from different `instance_id`s.
- MoR seller eligibility for a BiH company is unverified; if Paddle does not onboard BiH sellers, the default changes before 1.0.

## Open questions

1. **Legal (product owner with lawyer):** text of the proprietary EULA and the CLA for external contributions; governing law and venue (BiH, or configurable per contract as the US terms require).
2. **Legal:** confirm that distributing proprietary `/ee` modules together with the AGPL core in one artifact is covered by PROJEKTRA's dual licensing of its own core, and how third-party AGPL contributions without CLA would affect this.
3. **Accountant:** tax treatment and invoice wording for PROJEKTRA's license invoices to EU business customers (export of services from BiH; recipient liable under the customer's VAT law, for Germany [§13b UStG](https://www.gesetze-im-internet.de/ustg_1980/__13b.html)). See ADR-0004.
4. **Accountant:** tax treatment of PROJEKTRA's invoices to a merchant of record (Paddle is established in the UK and the US).
5. **Product owner:** Founding Customer plan window (proposal: contracts signed from 01.12.2026 to 31.03.2027) and maximum count (proposal: 20 customers).
6. **Product owner:** confirm the 14-day grace after expiry and whether over-seat true-up is invoiced pro rata or for full months.
7. **Product owner:** confirm Paddle as default after the seller-eligibility check; fallback candidate Polar or Freemius.
