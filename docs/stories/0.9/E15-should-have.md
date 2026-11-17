# E15 Should have: quotes with XLSX costing, absence calendar basic, expenses

These items are built in 0.9 only if the release is on schedule on 26 November; otherwise they move to 0.10 (18 December 2026). Given the capacity check in README.md, the planning assumption is that they move to 0.10.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0995 | Quotes with line items, versions and costing view | Should | 18 | L3 |
| S-0996 | Quote export as XLSX costing sheet with formulas | Should | 8 | L3 |
| S-0997 | Quote documents and one-click project from an accepted quote | Should | 14 | L3 |
| S-0998 | Absence calendar (basic) | Should | 20 | L3 |
| S-0999 | Expenses (mileage, hotel, per diems) | Should | 14 | L3 |

Epic total: Should 74 h.

---

### S-0995 Quotes with line items, versions and costing view

| Field | Value |
|---|---|
| Persona | Project lead / sales |
| Priority | Should |
| Estimate | 18 h (build 9, tests 6, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0939, S-0950, S-0959, S-0962 |
| Modules | quotes |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As a project lead, I want quotes with role-based hour lines, fixed items, optional items, discounts, versions and a costing view, so that I can price work with known margins.

```gherkin
Scenario: Versions and status
  Given quote Q-2026-014 v1 in status "sent"
  When the project lead changes a line
  Then version v2 is created and v1 remains unchanged
  And statuses draft, sent, accepted, declined and expired with a validity date are supported

Scenario: Costing view
  Given a line "40 h Senior IT Project Lead" at sales rate 100 EUR/h and cost rate 60 EUR/h
  Then the costing view shows cost 2,400.00 EUR, sales 4,000.00 EUR and margin 1,600.00 EUR (40 %)
  And cost and margin are visible only with "rates.view_cost"
```

---

### S-0996 Quote export as XLSX costing sheet with formulas

| Field | Value |
|---|---|
| Persona | Project lead / sales |
| Priority | Should |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0995, S-0956 |
| Modules | quotes |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a project lead, I want every quote exportable as an Excel costing sheet with formulas, so that I can optimize prices outside the product.

```gherkin
Scenario: Formulas in the costing sheet
  Then each line has quantity, cost rate, sales rate, cost, sales and margin, where cost, sales and margin are formulas
  And changing a sales rate in the sheet updates margin and totals
  And the sheet totals equal the quote totals in the product
```

---

### S-0997 Quote documents and one-click project from an accepted quote

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Should |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0995, S-0955, S-0940 to S-0942 |
| Modules | quotes, projects |
| Edition | Community |
| Reviewers | ux, finance-tax, qa |

**Story:** As a project lead, I want quote documents in the client's language and one click to turn an accepted quote into a project, so that no data is typed twice.

```gherkin
Scenario: Accepted quote creates the project
  Given quote Q-2026-014 v2 is marked accepted
  When the project lead clicks "Create project"
  Then a project, a contract with budget, work packages from the line items and contract rates are created
  And the project links back to the quote

Scenario: Quote document
  Then the quote is exported as PDF and DOCX from templates in the client's document language
```

---

### S-0998 Absence calendar (basic)

| Field | Value |
|---|---|
| Persona | Employee and project lead |
| Priority | Should |
| Estimate | 20 h (build 10, tests 6, review and docs 4) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0906, S-0912, S-0944 |
| Modules | absences |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As an employee, I want to request leave and see my team's absences, so that planning and time tracking reflect who is available.

```gherkin
Scenario: Request and approve
  When an employee requests vacation from 22.12.2026 to 02.01.2027
  Then the approver is notified and can approve or reject with a reason
  And working days exclude weekends and public holidays of the employee's region

Scenario: Holidays
  Then public holidays for Baden-Württemberg and the Federation of Bosnia and Herzegovina for 2026 and 2027 are included

Scenario: Privacy
  Then the reason "sick leave" is visible only to authorized roles; others see "absent"

Scenario: Team view and timesheet
  Then a team month view shows absences, and approved absence days appear in the weekly timesheet as non-bookable days
```

---

### S-0999 Expenses (mileage, hotel, per diems)

| Field | Value |
|---|---|
| Persona | Employee and controller |
| Priority | Should |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0944, S-0947, S-0961, S-0909 |
| Modules | expenses |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As an employee, I want to record mileage, hotel and per diems with receipts on a project, so that re-billable costs reach the invoice.

```gherkin
Scenario: Mileage
  Given a mileage rate of 0.30 EUR/km
  When an employee records 120 km on project "GG Operations"
  Then the expense is 36.00 EUR, marked re-billable and goes through the same approval as time

Scenario: Receipts
  Then receipts are uploaded safely (type and size checked, stored outside the web root)

Scenario: To the invoice
  Then approved re-billable expenses can be added to an invoice draft with their own tax case
```
