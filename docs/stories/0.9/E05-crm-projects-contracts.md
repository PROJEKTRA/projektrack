# E05 Clients, projects, contracts and work packages

Epic goal: the master data that time tracking, service reports and invoices hang on: clients with tax status, projects, contracts with budgets and a work breakdown structure.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0939 | Clients and contacts with one-dialog onboarding and tax case preview | Must | 16 | L2 |
| S-0940 | Projects with team and project roles | Must | 14 | L2 |
| S-0941 | Contracts with budgets, role quotas and alerts | Must | 16 | L2 |
| S-0942 | Work breakdown structure and service categories | Must | 18 | L2 |
| S-0943 | Client activity timeline | Should | 6 | L2 |

Epic total: Must 64 h, Should 6 h.

---

### S-0939 Clients and contacts with one-dialog onboarding and tax case preview

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 16 h (build 8, tests 5, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0902, S-0906, S-0959 (tax case preview uses the tax engine contract; stub until L3) |
| Modules | crm |
| Edition | Community |
| Reviewers | finance-tax, ux, security, qa |

**Story:** As a controller, I want to create a client with contacts, billing data, VAT ID, tax status and invoice preferences in one dialog, so that invoices to this client are correct from the first one.

```gherkin
Scenario: Create a client in one dialog
  When the controller creates "Learnteq Demo GmbH" in Germany with VAT ID "DE999999999", tax status "EU business", document language "en", currency "EUR", payment terms "14 days" and preferred e-invoice format "ZUGFeRD"
  Then the client, its billing address and its first contact are saved together

Scenario: Tax case preview
  Given the tenant is PROJEKTRA in Bosnia and Herzegovina
  When the client above is saved
  Then the dialog shows "Invoices to this client: no BiH PDV, place of supply abroad (notice on invoice)" with its reason

Scenario: VAT ID validation
  Then the VAT ID format is validated per country
  And an optional VIES check can be enabled by the admin (off by default, no external call otherwise)

Scenario: Public sector
  Given a client marked as public sector
  Then a Leitweg-ID field is shown and validated for format

Scenario: Sites and contacts
  Then a client can have several sites and contacts, and contacts can be marked as invoice recipients
```

Implementation note (23.11.2026): module `crm`, dialog and API with the same checks. Payment terms are a number of days; VIES is not built. The tax case preview follows with the tax engine (S-0959). A module is switched on for new companies; for an existing one: `php artisan projektrack:modules:enable <tenant> crm`.

---

### S-0940 Projects with team and project roles

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0939, S-0906 |
| Modules | projects |
| Edition | Community |
| Reviewers | ux, security, qa |

**Story:** As a project lead, I want projects with client, status, period, billing model, budget and a team with project roles, so that work and responsibilities are clear.

```gherkin
Scenario: Create project
  When the project lead creates "GG Operations" for client "Learnteq Demo GmbH" with billing model "time and material", period 01.10.2026 to 31.03.2027 and budget 300 h / 30,000 EUR
  Then the project is listed with status "active" and health "green"

Scenario: Project team
  When the project lead adds three employees with the project roles Junior Consultant, Senior IT Project Lead and Technical Architect
  Then only these people can book on the project's work packages

Scenario: Health indicator
  Given 92 % of the hour budget is consumed
  Then the health indicator turns "amber" (threshold configurable)

Scenario: Budget money hidden without permission
  Then users without "projects.view_budget_money" see hours only
```

---

### S-0941 Contracts with budgets, role quotas and alerts

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 16 h (build 8, tests 6, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0940 |
| Modules | contracts |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want contracts per project with type, budget, term, rates and role quotas, so that I can see how much of a statement of work is used and be warned in time.

```gherkin
Scenario: Contract types
  Then a contract can be framework agreement with call-offs, time and material, fixed price, hour package, monthly retainer or maintenance/support

Scenario: Budget alerts
  Given contract "SOW-2026-03" with 100 h budget and default alerts at 70 %, 90 % and 100 %
  When approved bookings reach 70 h
  Then the project lead and controller are notified once for the 70 % threshold

Scenario: Role quota
  Given a quota of 40 h Senior IT Project Lead per month
  When bookings of that role exceed 40 h in November
  Then the contract view shows the overrun and the role quota warning

Scenario: Several contracts per project
  Then a project can have several contracts, and each work package is linked to at most one contract
```

---

### S-0942 Work breakdown structure and service categories

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must |
| Estimate | 18 h (build 10, tests 5, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0940, S-0941 |
| Modules | projects |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a project lead, I want nested work packages with automatic numbering, drag and drop and service categories, so that the project structure matches how we deliver and report.

```gherkin
Scenario: Nested numbering
  Given work packages "Project management", "Consulting" and "Development" with "Development > Backend"
  Then they are numbered 1, 2, 3 and 3.1
  When "Backend" is dragged under "Consulting"
  Then it is renumbered 2.1 and existing time entries keep their link

Scenario: Work package attributes
  Then a work package has owner, assignees, planned hours, planned cost, start and end, percent complete, status, billable flag, linked contract and service category

Scenario: Configurable, translatable service categories
  Then the tenant can manage service categories (project management, consulting, development, operations, support, daily stand-up) with names per language

Scenario: Keyboard and RTL
  Then the tree can be reordered with the keyboard and is mirrored for ar
```

---

### S-0943 Client activity timeline

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Should |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0939, S-0940 |
| Modules | crm |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a project lead, I want a timeline of projects, service reports and invoices per client, so that I see the relationship at a glance.

```gherkin
Scenario: Timeline respects permissions
  Then the client page lists projects, service reports and invoices in date order
  And invoice amounts appear only for users with invoice permissions
```
