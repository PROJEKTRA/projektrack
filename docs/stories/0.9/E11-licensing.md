# E11 Licensing

Epic goal: PROJEKTRA sells and delivers Projektrack licenses from inside Projektrack. Channel 1 only (direct B2B sale with invoice and bank transfer); the 30-day trial and the self-service checkout are not part of 0.9 (assumption A-11). Licenses are signed Ed25519 tokens validated offline; signing keys never live in the database.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0973 | Licensing domain and Founding Customer plan | Must | 12 | L3 |
| S-0974 | Ed25519 license signing and offline validation | Must | 12 | L3 |
| S-0975 | Direct sale flow (order, invoice, pending payment, activation email) | Must | 16 | L3 |
| S-0976 | License screen and activation (online code, offline file) | Must | 14 | L3 |
| S-0977 | Check-in, soft enforcement and edition gating | Must | 16 | L3 |
| S-0978 | License portal (basic) | Must | 14 | L3 |
| S-0979 | Renewal reminders and "Add users" order request | Should | 6 | L3 |

Epic total: Must 84 h, Should 6 h.

---

### S-0973 Licensing domain and Founding Customer plan

| Field | Value |
|---|---|
| Persona | PROJEKTRA licensing manager |
| Priority | Must |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0939, S-0901 |
| Modules | licensing (ee) |
| Edition | Business (runs on the PROJEKTRA instance) |
| Reviewers | finance-tax, security, qa |

**Story:** As PROJEKTRA's licensing manager, I want license customers, plans, subscriptions, licenses and activations with full history, so that every sold license is traceable.

```gherkin
Scenario: Plans are data
  Then plans, prices, currencies and add-ons are configurable records, not code
  And the plan "Founding Customer" exists: Business edition, price locked for 24 months, onboarding package included

Scenario: Subscription for a CRM client
  When the manager creates a subscription for client "Learnteq Demo GmbH" with 10 full and 5 light users, yearly term
  Then the license customer is linked to the CRM client and every change is stored in the history
```

---

### S-0974 Ed25519 license signing and offline validation

| Field | Value |
|---|---|
| Persona | Instance operator |
| Priority | Must |
| Estimate | 12 h (build 5, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0973 |
| Modules | licensing (ee), core (validator) |
| Edition | Business (issuer), Community (validator) |
| Reviewers | security, qa |

**Story:** As an instance operator, I want licenses as signed tokens validated offline, so that licensing works without any third-party license server and cannot be forged.

```gherkin
Scenario: Signed token
  Then a license contains edition, full and light user counts, expiry, instance and domain binding and add-ons, signed with Ed25519

Scenario: Tampering detected
  Given a license whose user count was edited
  Then validation fails and the instance stays on its previous valid license or Community

Scenario: Keys outside the database
  Then the private key is read from a key file or KMS reference outside the database
  And the public key ships with every release
  And key rotation is documented and tested with two public keys accepted during rotation
```

Implementation note (23.11.2026): `App\Licensing` reads, verifies and installs licenses offline (`projektrack:license:install` and `license:show`, hourly check). `ee/licensing/issue.php` issues them on a machine of the licensor with an encrypted key file; it is not part of a release. Built before S-0973, which needs the CRM, so that a signed license can be issued on 1 December. Not in this story: license screen and online activation (S-0976), check-in, seat counting and gating (S-0977), key-status document, issuing as a queued and audited job on the PROJEKTRA instance (S-0973, S-0975). Differences from ADR-0003: issuing is a tool on a trusted machine without an audit entry until S-0975; validation runs on each read and hourly, not at boot; the domain warning comes with the license screen (S-0976) and `binding.domains` holds host names; the expiry grace is at most 90 days. A release without license keys is refused by the release workflow. Still due: a threat model for licensing and a CI check that the core never references `Ee\` (BL-028). Open for the product owner: generate the license key and ship its public key. Guide: `docs/admin/licensing.md`.

---

### S-0975 Direct sale flow (order, invoice, pending payment, activation email)

| Field | Value |
|---|---|
| Persona | PROJEKTRA licensing manager |
| Priority | Must |
| Estimate | 16 h (build 8, tests 6, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0973, S-0974, S-0961, S-0963, S-0966 |
| Modules | licensing (ee), invoicing |
| Edition | Business |
| Reviewers | finance-tax, security, qa |

**Story:** As PROJEKTRA's licensing manager, I want an accepted order to produce the invoice and a pending-payment license automatically, so that the customer can start immediately and the license activates on payment.

```gherkin
Scenario: Order to license
  Given the order for "Learnteq Demo GmbH" on the Founding Customer plan is accepted
  When the manager confirms the order
  Then an invoice in EUR, English, with the correct tax case (no BiH PDV, place-of-supply notice) is drafted for finalization
  And a license in state "pending payment" with a 14-day grace period is issued
  And the customer admin receives an email with an activation code such as "PTK-7H4Q-M2XD-9KLA" and portal access

Scenario: Payment activates
  When the invoice is marked as paid
  Then the license switches to "active" automatically and the change is in the history

Scenario: Grace period ends unpaid
  Given 14 days have passed without payment
  Then the manager is notified and the license follows the soft enforcement rules of S-0977
```

---

### S-0976 License screen and activation (online code, offline file)

| Field | Value |
|---|---|
| Persona | Customer admin |
| Priority | Must |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0974 |
| Modules | core (license screen) |
| Edition | Community (screen), Business features unlocked by license |
| Reviewers | security, ux, qa |

**Story:** As a customer admin, I want to activate a license with a code or a file in one step and see what my plan includes, so that I never need a support ticket to license the product.

```gherkin
Scenario: Online activation
  When the admin enters the activation code in Admin > License > Activate
  Then the instance registers its instance ID and domain with the PROJEKTRA licensing service over TLS and installs the signed license in under one minute

Scenario: Offline license file
  Given an air-gapped instance
  When the admin uploads the license file
  Then the signature is validated locally and the license is installed

Scenario: Clear license screen
  Then the screen shows edition, full and light users used vs licensed, expiry, add-ons, last check-in and "What does my plan include?" in the admin's language
```

---

### S-0977 Check-in, soft enforcement and edition gating

| Field | Value |
|---|---|
| Persona | Customer admin |
| Priority | Must |
| Estimate | 16 h (build 7, tests 7, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0976, S-0901, S-0906 |
| Modules | core, licensing (ee) |
| Edition | Community and Business |
| Reviewers | security, qa |

**Story:** As a customer admin, I want my license refreshed automatically and limits enforced softly, so that renewals just work and we never lose data or access to Community functions.

```gherkin
Scenario: Daily check-in sends minimal data
  Then an activated instance checks in once a day with instance ID, version and active user counts only
  And receives an updated license after a renewal or seat change
  And if the check-in fails, the installed license stays valid until its expiry plus grace period

Scenario: Over the licensed user count
  Given 10 full users are licensed and an 11th is activated
  Then admins see a warning and a 30-day grace period starts; nothing is locked

Scenario: Expiry
  Given the license expired and the grace period ended
  Then Business features become read-only, Community features keep working, and no data is deleted

Scenario: Edition gating
  Then modules marked "business" in module.json are available only with a valid Business license
```

---

### S-0978 License portal (basic)

| Field | Value |
|---|---|
| Persona | Customer admin (license customer) |
| Priority | Must (simplification candidate for a managed first customer) |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0973, S-0975, S-0987 |
| Modules | licensing (ee), portal |
| Edition | Business (runs on the PROJEKTRA instance) |
| Reviewers | security, ux, qa |

**Story:** As a license customer, I want a portal to see subscriptions and invoices, download license files and manage activations, so that I can self-serve.

```gherkin
Scenario: Portal content
  Then a portal user sees only their own subscriptions, license invoices, renewal dates and activations

Scenario: Move a license
  When the customer deactivates the old instance in the portal
  Then the license can be activated on a new server

Scenario: Portal users are free
  Then portal users do not count as licensed users
```

---

### S-0979 Renewal reminders and "Add users" order request

| Field | Value |
|---|---|
| Persona | Customer admin |
| Priority | Should |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0975, S-0977 |
| Modules | licensing (ee) |
| Edition | Business |
| Reviewers | qa |

**Story:** As a customer admin, I want renewal reminders and an "Add users" button, so that I never run out of seats by surprise.

```gherkin
Scenario: Reminders
  Then reminders are sent 60, 30 and 7 days before renewal

Scenario: Add users (channel 1)
  When the admin clicks "Add users" at the licensed limit
  Then a prefilled order request is sent to PROJEKTRA and a 14-day grace period is granted
```
