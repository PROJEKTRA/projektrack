# E01 Foundation

Release 0.9 "Founding Edition" (delivery 1 December 2026). Epic goal: a secure, multi-tenant, modular core that every other epic builds on. Tenant isolation, authentication, permissions and the audit log are not negotiable and are never cut.

Estimates are ideal developer hours and include implementation, automated tests (Pest, Vitest, Playwright where relevant, on MySQL, MariaDB and PostgreSQL), review fixes and docs. The split is given as build / tests / review and docs.

Sprint key: L1 Foundation (18-21 Nov), L2 Core (22-25 Nov), L3 Money and onboarding (26-28 Nov), Hardening (29-30 Nov).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0901 | Module registry with runtime enable/disable | Must | 16 | L1 |
| S-0902 | Multi-tenancy with central tenant scope | Must | 18 | L1 |
| S-0903 | Authentication and session security | Must | 14 | L1 |
| S-0904 | TOTP two-factor authentication enforced per role | Must | 10 | L1 |
| S-0905 | Passkeys (WebAuthn) | Must | 12 | L1 |
| S-0906 | Roles, project roles and field-level permissions | Must | 22 | L1 |
| S-0907 | Tamper-evident audit log | Must | 10 | L1 |
| S-0908 | CI quality and security pipeline with database matrix | Must | 16 | L1 |
| S-0909 | Web security baseline | Must | 12 | L1 |
| S-0910 | Brand theme, light/dark mode and app shell | Must | 22 | L1 |
| S-0911 | Central brand and legal entity configuration | Must | 6 | L1 |
| S-0912 | Settings framework and notifications | Must | 12 | L1 |
| S-0913 | REST API foundation with OpenAPI | Must | 10 | L1 |
| S-0914 | Command palette and global search | Should | 10 | L2 |
| S-0915 | Independent security review and threat models | Must | 18 | Hardening |

Epic total: Must 198 h, Should 10 h.

---

### S-0901 Module registry with runtime enable/disable

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 16 h (build 9, tests 5, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | Repository and stack running (Phase 0) |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a tenant admin, I want to enable and disable modules in the admin UI with dependencies explained in plain language, so that my team only sees what we use and no data is lost when a module is switched off.

```gherkin
Scenario: Module manifest is loaded and validated
  Given a module folder "modules/Timesheets" with a valid module.json
  When the application boots
  Then the module is listed in Admin > Modules with name, version, edition and required modules
  And a module.json with a missing "key" or unknown "requires" entry is rejected with a logged error

Scenario: Dependencies are explained before enabling
  Given the module "Service reports" requires "Time tracking"
  And "Time tracking" is disabled for the tenant
  When the admin enables "Service reports"
  Then the admin is told in plain language that "Time tracking" will be enabled as well
  And both modules are enabled only after confirmation

Scenario: Disabled module keeps its data and switches off everything else
  Given the module "Time tracking" is enabled and has 120 time entries
  When the admin disables it
  Then its navigation, routes, API endpoints, widgets and scheduled jobs are unavailable for that tenant
  And requests to its routes return 404 without revealing the module name
  And re-enabling the module shows all 120 time entries unchanged

Scenario: Feature flag on top of a module
  Given a feature flag "timesheets.timer" set to off for tenant "Learnteq Demo"
  Then the timer is hidden for that tenant only
```

---

### S-0902 Multi-tenancy with central tenant scope

| Field | Value |
|---|---|
| Persona | Instance operator (PROJEKTRA operations) |
| Priority | Must |
| Estimate | 18 h (build 8, tests 7, review and docs 3) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0901 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As an instance operator, I want several companies in one instance with strictly separated data, so that each customer only ever sees its own records.

```gherkin
Scenario: Tenant scope is applied centrally
  Given tenants "PROJEKTRA IT Consulting" and "Demo Company d.o.o."
  And a user of "Demo Company d.o.o."
  When the user lists clients, projects, time entries or invoices through the UI or the API
  Then only records of "Demo Company d.o.o." are returned

Scenario: Active cross-tenant access attempts fail
  Given a user of tenant A knows the ID of a project of tenant B
  When the user requests that project by URL, API, export, file download or search
  Then every request returns 404
  And the attempt is written to the audit log

Scenario: Query without tenant context is refused
  Given a background job without tenant context
  When it queries a tenant-scoped model
  Then an exception is raised instead of returning data of all tenants

Scenario: Per-tenant settings
  Then each tenant has its own branding, numbering, tax and bank details, default language, currency and time zone
```

Notes: the cross-tenant test suite runs for every new model through a shared test trait; this is part of the Definition of Done of every later story.

---

### S-0903 Authentication and session security

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0902 |
| Modules | core |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As an employee, I want to sign in securely and stay signed in safely, so that my account and my company data are protected.

```gherkin
Scenario: Password storage and policy
  When a user sets a password
  Then it is hashed with Argon2id
  And passwords shorter than 12 characters are rejected with an actionable message in the user's language

Scenario: Lockout and rate limiting
  Given 5 failed sign-in attempts for one account within 15 minutes
  Then further attempts are throttled with an increasing delay
  And the response does not reveal whether the account exists

Scenario: Re-authentication for sensitive actions
  Given a signed-in controller whose last authentication is older than 15 minutes
  When the controller finalizes an invoice, changes permissions or exports data
  Then the password or a second factor is requested again

Scenario: Session handling
  Then session cookies are Secure, HttpOnly and SameSite=Lax
  And the session ID is regenerated on sign-in and invalidated on sign-out
  And a user can sign out all other sessions from the profile page

Scenario: Password reset
  When a user requests a password reset
  Then a single-use link valid for 60 minutes is sent
  And the response is identical for known and unknown email addresses
```

---

### S-0904 TOTP two-factor authentication enforced per role

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0903, S-0906 |
| Modules | core |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a tenant admin, I want two-factor authentication enforced for admin and finance roles by default, so that a stolen password alone cannot reach money or permissions.

```gherkin
Scenario: Enforced 2FA for finance roles
  Given the role "Controller" requires 2FA (default on for Admin and Controller)
  When a controller without 2FA signs in
  Then the controller must set up TOTP before any other screen is reachable

Scenario: Recovery codes
  When a user activates TOTP
  Then 10 single-use recovery codes are shown once and stored hashed
  And using a recovery code is written to the audit log

Scenario: Admin resets 2FA of a user
  When an admin resets 2FA for a user
  Then the admin must re-authenticate
  And the user is notified by email
```

---

### S-0905 Passkeys (WebAuthn)

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must (cut candidate, see README) |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0903 |
| Modules | core |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As an employee, I want to sign in with a passkey, so that I do not need to type passwords or codes.

```gherkin
Scenario: Register and use a passkey
  Given a signed-in user on a browser with WebAuthn support
  When the user registers a passkey named "Work laptop"
  Then the user can sign in with that passkey without a password
  And a passkey counts as a second factor for enforced 2FA

Scenario: Manage passkeys
  Then the user can list, rename and revoke passkeys
  And revoking a passkey is written to the audit log
```

---

### S-0906 Roles, project roles and field-level permissions

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 22 h (build 11, tests 8, review and docs 3) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0901, S-0902 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a tenant admin, I want global roles, project roles and field-level permissions with deny by default, so that people see exactly what their job needs and nothing more.

```gherkin
Scenario: Deny by default
  Given a new permission "invoices.finalize" registered by a module
  Then no role has it until it is granted explicitly
  And every controller action and API endpoint is covered by a policy (CI test fails on unprotected routes)

Scenario: Global and project roles
  Given the global roles Admin, Controller, Project Lead, Employee, External/Freelancer and Customer
  And "Ana Test" is Employee globally and Project Lead in project "GG Operations"
  Then "Ana Test" can approve times in "GG Operations" only

Scenario: Field-level permission for rates
  Given an employee without permission "timesheets.view_rates"
  When the employee opens a timesheet, a project, a service report preview or the API
  Then no rate, cost, amount or margin is contained in the page, the JSON props or the API response

Scenario: User types for licensing
  Then every user has a type Full, Light or Portal
  And Light users can only access time, expenses, own tasks and approvals
```

---

### S-0907 Tamper-evident audit log

| Field | Value |
|---|---|
| Persona | Auditor / controller |
| Priority | Must |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0902 |
| Modules | core |
| Edition | Community |
| Reviewers | security, finance-tax, qa |

**Story:** As an auditor, I want an immutable, hash-chained audit log of logins, permission changes, financial records, exports and settings, so that I can prove who did what and detect manipulation.

```gherkin
Scenario: Events are logged
  When a user signs in, changes a role, finalizes an invoice, exports data or changes a setting
  Then an audit entry with tenant, actor, action, subject, timestamp (UTC) and IP is written

Scenario: Hash chain detects tampering
  Given 1,000 audit entries
  When one entry is changed directly in the database
  Then "projektrack audit:verify" reports the first broken entry
  And the admin UI shows an integrity warning

Scenario: No update or delete
  Then the application offers no way to edit or delete audit entries
```

---

### S-0908 CI quality and security pipeline with database matrix

| Field | Value |
|---|---|
| Persona | Maintainer (lead engineer) |
| Priority | Must |
| Estimate | 16 h (build 10, tests 3, review and docs 3) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | Repository (Phase 0) |
| Modules | core, deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a maintainer, I want every change checked automatically for quality, security and database compatibility, so that defects and vulnerabilities do not reach a release.

```gherkin
Scenario: Quality gates
  When a pull request is opened
  Then PHPStan at max level, Pint, ESLint, Prettier and vue-tsc run and must pass
  And domain test coverage below 85 % fails the build

Scenario: Database and infrastructure matrix
  Then the full Pest suite runs on MySQL 8.4, MariaDB 11.4 and PostgreSQL 16
  And an "AMP profile" job runs without Redis, Docker services or Node at runtime (database queue, file cache)

Scenario: Supply chain checks
  Then composer audit, npm audit, Semgrep and secret scanning run on every pull request
  And a CycloneDX SBOM is produced for every build
  And a known critical vulnerability fails the build
```

---

### S-0909 Web security baseline

| Field | Value |
|---|---|
| Persona | Security officer of a customer |
| Priority | Must |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0903 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a customer's security officer, I want the application to follow a strict web security baseline, so that common web attacks are blocked by design.

```gherkin
Scenario: Security headers
  Then every response carries HSTS, a strict Content Security Policy with nonces and no inline scripts, X-Content-Type-Options and frame-ancestors 'none'

Scenario: Safe file uploads
  When a user uploads a file
  Then type and size are validated server side
  And files are stored outside the web root and are never executable
  And downloads are served only through signed, expiring URLs

Scenario: Secrets encrypted at rest
  Then SMTP passwords, API tokens and signing material references are encrypted at application level
  And the encryption key is not stored in the database

Scenario: CSRF and output encoding
  Then state-changing requests without a valid CSRF token are rejected
  And user input rendered in pages, PDFs and emails is encoded
```

---

### S-0910 Brand theme, light/dark mode and app shell

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must |
| Estimate | 22 h (build 14, tests 4, review and docs 4) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0916 (strings), S-0920 (logical CSS) |
| Modules | core (frontend) |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As an employee, I want a calm, consistent interface in the PROJEKTRA brand with light and dark mode, so that daily work is pleasant and readable.

```gherkin
Scenario: Design tokens
  Then all colors, radii, fonts and motion values come from design tokens (CSS custom properties and Tailwind theme)
  And a white-label theme can change the look by swapping tokens only

Scenario: Light and dark mode
  When a user switches to dark mode
  Then all screens use the dark tokens
  And text and controls meet WCAG 2.2 AA contrast in both modes

Scenario: Self-hosted fonts and no external requests
  Then Mona Sans, Golos Text, Noto Naskh Arabic and Vazirmatn are served from the instance
  And no page requests a resource from a third-party host

Scenario: App shell and base components
  Then the app shell (sidebar, top bar, breadcrumbs, user menu), buttons, inputs, selects, tables, dialogs, toasts and empty states exist as documented components
  And amounts, hours and dates use tabular numbers
  And reduced motion preferences are respected
```

Notes: official PROJEKTRA logo SVGs are pending (assumption A-02); a neutral placeholder is used and listed as open item.

---

### S-0911 Central brand and legal entity configuration

| Field | Value |
|---|---|
| Persona | Instance operator |
| Priority | Must |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0912, S-0916 |
| Modules | core |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As an instance operator, I want the product name, logo, colors and legal entity configured in one place, so that white-label tenants and documents always show the right brand and licensor.

```gherkin
Scenario: Brand placeholder
  Given the string "core:welcome" with text "Welcome to {brand}"
  And a tenant brand name "Acme Projects"
  Then the user of that tenant sees "Welcome to Acme Projects"
  And no product name is hard-coded in code, templates or strings (CI check)

Scenario: Legal entity of the licensor
  Then the licensor data (PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik, address, JIB, MBS) is stored once
  And it is used by the About screen, license documents and license invoices
```

---

### S-0912 Settings framework and notifications

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 12 h (build 7, tests 3, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0901, S-0902 |
| Modules | core |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a tenant admin, I want every setting editable in the admin UI and users notified in-app and by email, so that nothing requires editing files and nobody misses an approval.

```gherkin
Scenario: Settings from module schema
  Given a module declares settings in settings.schema.json
  Then Admin > Settings renders a validated form for them with defaults and translated labels

Scenario: Notifications
  When a week is submitted for approval
  Then the approver gets an in-app notification and an email in the approver's language
  And each user can turn email notifications per type on or off
  And emails contain no tracking pixels and no rewritten links
```

---

### S-0913 REST API foundation with OpenAPI

| Field | Value |
|---|---|
| Persona | Integration developer at a customer |
| Priority | Must |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0906 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As an integration developer, I want a versioned REST API with personal access tokens and an OpenAPI description, so that every UI function can be automated.

```gherkin
Scenario: Token with scopes
  When a user creates an API token with scope "timesheets:read"
  Then the token can read time entries the user may see and nothing else
  And the token is shown once and stored hashed

Scenario: OpenAPI
  Then /api/v1 endpoints are described in an OpenAPI document generated in CI
  And CI fails if an API route is missing from the document

Scenario: Same permissions and tenant scope as the UI
  Then API requests are subject to the same policies, field permissions and tenant scope as the UI
```

---

### S-0914 Command palette and global search

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Should |
| Estimate | 10 h (build 6, tests 2, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0939, S-0940 |
| Modules | core |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a project lead, I want a command palette (Ctrl/Cmd+K) with search across clients, projects and actions, so that I reach anything in a few keystrokes.

```gherkin
Scenario: Search and act
  When the user presses Ctrl+K and types "GG Op"
  Then the project "GG Operations" is suggested together with "Book time on GG Operations"
  And results respect permissions and tenant scope
```

---

### S-0915 Independent security review and threat models

| Field | Value |
|---|---|
| Persona | Security officer of a customer |
| Priority | Must |
| Estimate | 18 h (review 10, fixes buffer 6, docs 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | All Must stories of L1 to L3 |
| Modules | all |
| Edition | Community and Business |
| Reviewers | security (independent reviewer who did not write the code), qa |

**Story:** As a customer's security officer, I want an independent security review and STRIDE threat models for every 0.9 module, so that I can trust the release with our project and financial data.

```gherkin
Scenario: Threat models exist
  Then docs/security/threat-models contains a STRIDE model for core, time tracking, service reports, invoicing, e-invoicing and licensing

Scenario: Review findings block release
  Given the independent review finds an issue rated high or critical
  Then the go/no-go on 30 Nov 16:00 is "no-go" until the issue is fixed and retested

Scenario: DAST run
  Then an OWASP ZAP baseline scan against the release candidate shows no high findings
```
