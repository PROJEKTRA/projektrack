# E04 Onboarding

Epic goal: a new customer company is productive on day one without a consultant. Every step is skippable, resumable and reachable again under "Setup". Client onboarding (one dialog) is delivered with the client entity in S-0939.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0930 | Workspace wizard | Must | 16 | L3 |
| S-0931 | Team invitations with role templates | Must | 12 | L3 |
| S-0932 | Permission matrix editor with explanations | Must | 12 | L3 |
| S-0933 | "View as this role/user" preview | Should | 8 | L3 |
| S-0934 | Role-based welcome flow on first login | Must | 10 | L3 |
| S-0935 | Project wizard with templates and kickoff checklist | Must | 18 | L3 |
| S-0936 | "Getting started" checklist | Must | 8 | L3 |
| S-0937 | Sample data option and synthetic demo dataset | Must | 14 | Hardening |
| S-0938 | Client and contact import wizard (CSV/Excel) | Should | 12 | L3 |

Epic total: Must 90 h, Should 20 h.

---

### S-0930 Workspace wizard

| Field | Value |
|---|---|
| Persona | Tenant admin (new customer) |
| Priority | Must (simplification candidate) |
| Estimate | 16 h (build 9, tests 4, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0911, S-0912, S-0960 (country packs), S-0962 (numbering) |
| Modules | core, onboarding |
| Edition | Community |
| Reviewers | ux, finance-tax, qa |

**Story:** As a new tenant admin, I want a short wizard that sets up company data, country, language, currency, time zone, branding, numbering and VAT status, so that the workspace has correct defaults from the first minute.

```gherkin
Scenario: Country selects defaults
  Given the admin selects country "Germany", language "de", currency "EUR" and VAT status "VAT registered"
  Then the DE tax pack, German document templates and the time zone Europe/Berlin are applied
  And the invoice numbering preview shows "INV-2026-0001"

Scenario: Branding live preview
  When the admin uploads a logo and picks a brand color
  Then the preview shows the themed app shell and a sample invoice header
  And a color failing AA contrast for text is adjusted automatically or rejected with an explanation

Scenario: Skip and resume
  When the admin closes the wizard after step 3
  Then the next sign-in offers to continue at step 4
  And all steps remain available under Setup

Scenario: Start choice
  Then the admin can start empty, with sample data (S-0937) or with import (S-0938 when available)
```

---

### S-0931 Team invitations with role templates

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0906, S-0912, S-0940 |
| Modules | core, onboarding |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a tenant admin, I want to invite team members by email with a role template and projects, so that they land in the right place with the right rights.

```gherkin
Scenario: Invitation shows role and projects
  When the admin invites "employee.one@example.com" as "Employee" to project "GG Operations"
  Then the invitation email in the invitee's language names the role and the project
  And the link uses an unguessable token, expires after 7 days and can be revoked

Scenario: Role templates in plain language
  Then the templates Admin, Controller, Project Lead, Employee, External/Freelancer and Client each have a plain-language description
  And a template can be copied and adapted

Scenario: Rates at invitation
  Then the admin can set cost and sales rate per person or inherit them from the role

Scenario: License counter
  Given the licensed number of full users is reached
  Then the invitation still works and the admin sees the soft-limit warning of S-0977
```

---

### S-0932 Permission matrix editor with explanations

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must (simplification candidate) |
| Estimate | 12 h (build 7, tests 3, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0906 |
| Modules | core |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a tenant admin, I want a permission matrix with explanations and risk hints, so that I can adapt roles without accidentally exposing rates or invoices.

```gherkin
Scenario: Explanations and risk hints
  Then every permission has a plain-language explanation
  And rates, invoices and exports are marked as sensitive with a risk hint

Scenario: Least privilege defaults
  Then financial fields are hidden for Employee and External roles unless explicitly granted

Scenario: Changes are audited
  When a permission is granted or revoked
  Then re-authentication is required and the change is written to the audit log
```

---

### S-0933 "View as this role/user" preview

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Should |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0932 |
| Modules | core |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a tenant admin, I want to preview the product as a role or user, so that I can verify what they see before inviting them.

```gherkin
Scenario: Read-only preview
  When the admin chooses "View as Employee"
  Then the navigation and data match an Employee's view
  And no write action is possible in preview mode and the preview is audited
```

---

### S-0934 Role-based welcome flow on first login

| Field | Value |
|---|---|
| Persona | Employee (new team member) |
| Priority | Must (simplification candidate) |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0904, S-0931, S-0992 (tour engine) |
| Modules | onboarding |
| Edition | Community |
| Reviewers | ux, security, qa |

**Story:** As a new employee, I want a short welcome flow with profile, language, 2FA where required and a role tour, so that I can start booking time right away.

```gherkin
Scenario: New customer is productive within 10 minutes
  Given a new workspace created from the installer
  When the admin completes the workspace wizard, invites two team members, adds a client and creates a project from the template "Customer project time and material"
  Then the "Getting started" checklist shows these steps as done
  And an invited employee can book time on the project on first login after a role-specific tour

Scenario: Reduced flow for light and external users
  Given an External/Freelancer user
  Then the welcome flow covers profile, language and booking time only

Scenario: Enforced 2FA in the flow
  Given the user's role requires 2FA
  Then the welcome flow cannot be finished without setting up TOTP or a passkey
```

---

### S-0935 Project wizard with templates and kickoff checklist

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must (simplification candidate) |
| Estimate | 18 h (build 10, tests 5, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0939 to S-0942, S-0950 |
| Modules | projects, onboarding |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a project lead, I want to create a project blank or from a template in a guided wizard, so that client, contract, team, work packages and dates are set up in one go.

```gherkin
Scenario: Project from template
  Given the templates "Customer project time and material", "Customer project fixed price" and "Managed services / retainer"
  When the project lead chooses "Customer project time and material" for client "Learnteq Demo GmbH"
  Then the wizard asks for contract (budget, billing model, rates), team and project roles, and dates
  And work packages are generated from the template, including "1 Project management" and "2 Consulting"

Scenario: Blank project
  Then a project can be created blank with only name, client and project lead

Scenario: Kickoff checklist
  Then the new project has a configurable kickoff checklist (kickoff meeting, stakeholder list, communication plan, access granted, first service report date agreed)

Scenario: Templates are translatable
  Then template names and work package names appear in the tenant's language
```

---

### S-0936 "Getting started" checklist

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 8 h (build 4, tests 2, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0930, S-0931, S-0935, S-0944, S-0953, S-0961 |
| Modules | onboarding |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a tenant admin, I want a dashboard checklist with progress, so that I know the next step until my company is fully set up.

```gherkin
Scenario: Steps complete automatically
  Given the checklist "add team, define roles and rates, add first client, create first project, book first time entry, create first service report, issue first invoice"
  When the first time entry is booked by anyone in the tenant
  Then the step "book first time entry" is checked automatically and progress increases

Scenario: Skip and reopen
  Then each step can be skipped, and the checklist can be hidden and reopened from Setup
```

---

### S-0937 Sample data option and synthetic demo dataset

| Field | Value |
|---|---|
| Persona | Tenant admin evaluating the product; trainer |
| Priority | Must |
| Estimate | 14 h (build 8, tests 3, review and docs 3) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | All L2 and L3 domain stories |
| Modules | core, all 0.9 modules |
| Edition | Community |
| Reviewers | qa, finance-tax (sample invoices must be legally plausible) |

**Story:** As a tenant admin, I want to start with realistic synthetic sample data and remove it with one click, so that I can learn the product safely.

```gherkin
Scenario: Demo dataset
  When sample data is installed
  Then a sample company, 3 clients, 5 projects, 8 employees, 3 months of approved bookings, service reports and draft invoices exist
  And all names, addresses and VAT IDs are synthetic
  And demo rates are Junior Consultant 80 EUR/h, Senior IT Project Lead 100 EUR/h, Technical Architect 130 EUR/h

Scenario: Remove sample data
  When the admin clicks "Remove sample data"
  Then all sample records are removed and real records created afterwards remain
  And sample invoices were never finalized, so no numbering sequence is consumed
```

---

### S-0938 Client and contact import wizard (CSV/Excel)

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Should |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0939 |
| Modules | crm, onboarding |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a tenant admin, I want to import clients and contacts from CSV or Excel with column mapping, duplicate detection and dry run, so that I do not retype my client base.

```gherkin
Scenario: Dry run with duplicates
  Given a CSV with 50 clients of which 3 already exist by VAT ID
  When the admin runs the import as dry run
  Then the result shows 47 new, 3 duplicates and any row errors, and nothing is saved
  When the admin confirms
  Then 47 clients are created and the import is audited

Scenario: Malicious content
  Then cells starting with "=", "+", "-" or "@" are stored as text and never evaluated
```
