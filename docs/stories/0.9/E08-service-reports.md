# E08 Service reports

Epic goal: one-click service reports that fully replace monthly spreadsheets, in DOCX, PDF and XLSX (with real formulas), in the customer's document language, frozen once approved.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0953 | Service report engine with presets and totals | Must | 20 | L2 |
| S-0954 | PDF export (LTR and RTL) | Must | 10 | L2 |
| S-0955 | DOCX export and template management | Must | 16 | L2 |
| S-0956 | XLSX export with formulas and CSV | Must | 10 | L2 |
| S-0957 | Snapshot and versioning of approved reports | Must | 8 | L2 |
| S-0958 | Batch generation for a month as ZIP | Should | 8 | L2 |

Epic total: Must 64 h, Should 8 h.

---

### S-0953 Service report engine with presets and totals

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must |
| Estimate | 20 h (build 10, tests 7, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0944, S-0947, S-0950 |
| Modules | reports |
| Edition | Community |
| Reviewers | finance-tax, ux, security, qa |

**Story:** As a project lead, I want to generate a service report with one click from a preset, so that I deliver proof of work to the client in seconds.

```gherkin
Scenario: Monthly service report for a project with all employees
  Given a project "GG Operations" with contract "SOW-2026-03"
  And three employees with the roles Junior Consultant, Senior IT Project Lead and Technical Architect
  And approved time entries in October 2026
  When the project lead clicks "Service report > Project > Previous month"
  Then a document with all entries sorted by date in the locale date format is generated
  And it contains totals per role, per service category and a grand total
  And the totals in the XLSX export are formulas that equal the values in the PDF
  And the export is available as DOCX, PDF and XLSX

Scenario: Presets
  Then presets exist per work package, per employee (all projects or one project), per project with all employees, per contract and per customer
  And periods week, month, quarter and custom are available, default previous month

Scenario: Customer view vs internal view
  Given the preset "customer view"
  Then no cost rates or margins appear, and amounts appear only if the preset includes them
  And a user without "view_rates" cannot select a preset with amounts

Scenario: Contract budget consumption
  Then the header shows customer, project, contract, period, report number and budget used and remaining

Scenario: Document language
  Given the client's document language is de and the user's UI language is bs
  Then the report is generated in de with DD.MM.YYYY dates
```

---

### S-0954 PDF export (LTR and RTL)

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0953 |
| Modules | reports |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a project lead, I want a clean PDF with logo, totals and signature fields, so that the client can sign it.

```gherkin
Scenario: PDF on AMP
  Then the PDF is produced with mPDF without Docker, Node or Redis
  And it contains the tenant logo, header, entries, totals and signature fields

Scenario: Arabic PDF
  Given the document language ar
  Then the exported PDF service report is right-to-left with correctly shaped Arabic text

Scenario: Download is protected
  Then the PDF is served through a signed, expiring URL with X-Robots-Tag noindex
```

---

### S-0955 DOCX export and template management

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must (simplification candidate: built-in templates only) |
| Estimate | 16 h (build 9, tests 4, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0953, S-0909 (safe uploads) |
| Modules | reports |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a tenant admin, I want to upload Word templates with placeholders and assign them per client and language, so that service reports look like our existing documents.

```gherkin
Scenario: Built-in template
  Then a default DOCX template exists in bs, en and de

Scenario: Upload and assign
  When the admin uploads a DOCX with placeholders "${project.name}" and a table block for entries
  And assigns it to client "Learnteq Demo GmbH" for language de
  Then service reports for that client in de use it and a preview is available before saving

Scenario: Invalid or unsafe template
  Given a DOCX with macros or an unknown placeholder
  Then the upload is rejected with a list of problems
```

---

### S-0956 XLSX export with formulas and CSV

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 10 h (build 5, tests 4, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0953 |
| Modules | reports |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want XLSX exports whose totals are real formulas, so that totals stay correct when the client edits the sheet.

```gherkin
Scenario: Formulas
  Then each subtotal and the grand total in the XLSX are SUM or SUMIF formulas
  And after changing one hour cell, the totals recalculate correctly in a spreadsheet application
  And formula results equal the PDF values for the same report

Scenario: RTL sheet and CSV
  Then an ar report uses a right-to-left sheet view
  And a CSV export (UTF-8 with BOM) is available, with cells starting with "=", "+", "-" or "@" escaped
```

---

### S-0957 Snapshot and versioning of approved reports

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0953 |
| Modules | reports |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As a controller, I want an approved service report frozen with a hash, so that what the client received can always be proven.

```gherkin
Scenario: Freeze on approval
  When the project lead approves the October report
  Then its data and files are stored as an immutable snapshot with a SHA-256 hash

Scenario: New version instead of change
  Given an approved report SR-2026-0012
  When a correction entry changes October data
  Then the report is marked outdated and regenerating creates SR-2026-0012 v2
  And v1 remains available and unchanged
```

---

### S-0958 Batch generation for a month as ZIP

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Should |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0953 to S-0956 |
| Modules | reports |
| Edition | Community |
| Reviewers | qa |

**Story:** As a controller, I want all service reports of a month for all projects in one ZIP, so that month end takes minutes.

```gherkin
Scenario: Batch as background job
  When the controller starts "All projects > October 2026 > PDF"
  Then a background job creates one report per project and a ZIP download link is sent when finished
```
