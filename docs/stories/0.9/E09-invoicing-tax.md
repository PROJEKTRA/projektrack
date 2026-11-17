# E09 Invoicing and tax engine

Epic goal: legally correct invoices with VAT, reverse charge and exemptions for the DE and BA country packs, gapless numbering, immutability after finalization and cancellation instead of deletion. Legal correctness of invoices is never cut or simplified. All money and tax logic is written tests first and cross-checked by the finance and tax reviewer and QA before it counts as done.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0959 | Tax engine (tax case determination, reasons, overrides) | Must | 24 | L3 |
| S-0960 | Country packs DE, BA and generic EU | Must | 14 | L3 |
| S-0961 | Invoice drafting, including from service reports and times | Must | 20 | L3 |
| S-0962 | Gapless numbering sequences | Must | 8 | L3 |
| S-0963 | Finalization, immutability, cancellation and credit notes | Must | 12 | L3 |
| S-0964 | Invoice documents (PDF and DOCX) in the document language | Must | 14 | L3 |
| S-0965 | External fiscal system reference | Must | 4 | L3 |
| S-0966 | Payments and open items (basic) | Must | 8 | L3 |
| S-0967 | Down payment and final invoices | Should | 12 | L3 |

Epic total: Must 104 h, Should 12 h.

---

### S-0959 Tax engine (tax case determination, reasons, overrides)

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 24 h (build 9, tests 11, review and docs 4) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0939, S-0951 |
| Modules | invoicing (tax domain layer) |
| Edition | Community |
| Reviewers | finance-tax, qa, security |

**Story:** As a controller, I want the tax case of every invoice line determined automatically with its reason, so that I issue correct invoices without being a tax expert.

```gherkin
Scenario: Reverse charge invoice to an EU business customer
  Given the seller is a German company with a valid VAT ID
  And the customer is a business in Austria with a valid VAT ID
  When an invoice for consulting services is finalized
  Then the invoice shows 0 % VAT with tax case "reverse charge"
  And it contains the legal reverse charge notice in the customer's document language
  And it shows both VAT IDs
  And the ZUGFeRD XML uses the correct VAT category code and validates against EN 16931

Scenario: Domestic German invoice with mixed rates
  Given a German seller and a German business customer
  And one line at 19 % and one line at 7 %
  Then VAT is calculated per rate and shown in a tax summary per rate, rounded per rate on minor units

Scenario: Non-EU customer
  Given a German seller and a business customer in Switzerland
  Then the tax case is "not taxable in Germany (place of supply abroad)" with the matching notice

Scenario: Small business and exempt
  Given a German seller with status "small business (section 19 UStG)"
  Then no VAT is charged and the section 19 notice is printed

Scenario: Override only with permission and justification
  When a controller with "invoices.override_tax_case" changes the tax case
  Then a justification is mandatory, shown in the invoice history and written to the audit log
  And a user without the permission cannot override

Scenario: Edge cases covered by tests
  Then tests cover rounding per line and per total, credit notes, cancellation, currency conversion, customer without VAT ID (no reverse charge), and a change of customer tax status after drafting
```

---

### S-0960 Country packs DE, BA and generic EU

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 14 h (build 6, tests 5, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0959 |
| Modules | invoicing, taxpack-de, taxpack-ba, taxpack-eu |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want country packs that define rates, mandatory fields, legal notices, numbering and retention, so that invoices in Germany and Bosnia and Herzegovina follow local rules.

```gherkin
Scenario: Domestic invoice with VAT in Bosnia and Herzegovina
  Given the seller is a company in Bosnia and Herzegovina registered for VAT
  And the customer is a domestic business
  When an invoice is finalized
  Then PDV at 17 % is calculated per line and in total
  And the invoice is rendered in Bosnian by default

Scenario: BA seller to a German business customer
  Given the seller is PROJEKTRA (BA, VAT registered) and the customer is a German business with VAT ID
  When an invoice for consulting services is finalized
  Then no BiH PDV is charged and the place-of-supply notice of the BA pack is printed in English
  And the notice text is the one confirmed by PROJEKTRA's accountant (assumption A-04)

Scenario: Mandatory fields per pack
  Given the DE pack
  Then finalizing is blocked if seller tax number or VAT ID, service date or period, or customer address is missing, with inline hints

Scenario: Retention
  Then the DE pack sets retention to 10 years for invoices (GoBD) and the BA pack to the period confirmed by the accountant

Scenario: Packs are data
  Then rates and notices are versioned with validity dates and notices are translatable strings per language
```

---

### S-0961 Invoice drafting, including from service reports and times

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 20 h (build 10, tests 7, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0953, S-0957, S-0959, S-0950 |
| Modules | invoicing |
| Edition | Community |
| Reviewers | finance-tax, ux, security, qa |

**Story:** As a controller, I want to draft an invoice from an approved service report with one click plus confirmation, or manually with free lines, so that billing my work is fast and consistent.

```gherkin
Scenario: Invoice from service report
  Given the approved service report SR-2026-0012 for October with 42.5 h
  When the controller clicks "Create invoice" and confirms
  Then a draft invoice is created with lines grouped as configured (per role or per work package), the client's currency and document language
  And the included time entries are marked "in draft invoice" and cannot be invoiced twice

Scenario: Fixed price invoice
  Then a draft with free lines (description, quantity, unit, unit price, tax case) can be created for a fixed-price contract

Scenario: Draft is editable, totals are exact
  Then line and total amounts use integer minor units and the tax summary updates live

Scenario: Removing a draft releases time entries
  When a draft is discarded
  Then its time entries become invoiceable again and no number was consumed
```

---

### S-0962 Gapless numbering sequences

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 8 h (build 3, tests 4, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0902 |
| Modules | invoicing, core |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want gapless numbers per tenant and document type with a configurable format, so that numbering meets legal requirements.

```gherkin
Scenario: Format and yearly reset
  Given the format "INV-{YYYY}-{####}" with yearly reset
  Then the first finalized invoice of 2026 is INV-2026-0001 and the first of 2027 is INV-2027-0001

Scenario: Numbers only at finalization
  Then drafts have no number; the number is assigned in the same transaction as finalization

Scenario: Concurrency
  When 20 invoices are finalized in parallel on MySQL, MariaDB and PostgreSQL
  Then numbers 1 to 20 are each used exactly once without gaps
```

---

### S-0963 Finalization, immutability, cancellation and credit notes

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 12 h (build 5, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0961, S-0962, S-0907 |
| Modules | invoicing |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As a controller, I want finalized invoices to be immutable and corrected only by cancellation and re-issue, so that our books are audit-proof.

```gherkin
Scenario: Finalized invoice is immutable
  Given a finalized invoice INV-2026-0042
  When a user tries to change a line item
  Then the change is rejected
  And the system offers "Cancel and re-issue"
  And the numbering sequence stays gapless

Scenario: Cancellation invoice
  When the controller cancels INV-2026-0042
  Then a cancellation invoice with its own number references INV-2026-0042 and negates all amounts and taxes
  And the time entries of INV-2026-0042 become invoiceable again

Scenario: Credit note
  Then a partial credit note referencing the original invoice can be issued with its own number

Scenario: No deletion
  Then no UI, API or CLI path deletes a finalized invoice, and a database-level test confirms it
  And finalization stores a hash of the document and is written to the audit log
```

---

### S-0964 Invoice documents (PDF and DOCX) in the document language

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 14 h (build 7, tests 4, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0961, S-0960, S-0955 |
| Modules | invoicing |
| Edition | Community |
| Reviewers | finance-tax, ux, qa |

**Story:** As a controller, I want the invoice rendered in the client's document language with logo, bank details and all mandatory data, so that the client receives a correct, professional document.

```gherkin
Scenario: Mandatory content
  Then the PDF shows seller and buyer data, both VAT IDs where required, invoice number and date, service period, lines, tax summary per rate, tax case notice, payment terms and bank details

Scenario: Language independent of the creator
  Given a creator with UI language bs and a client with document language en
  Then the invoice is rendered in English with English legal notices

Scenario: Word and PDF
  Then the invoice is available as PDF and DOCX; the archived legal original is the finalized PDF

Scenario: Optional EPC QR code
  Given EUR currency and an IBAN
  Then an EPC QR code can be enabled per tenant
```

---

### S-0965 External fiscal system reference

| Field | Value |
|---|---|
| Persona | Controller (PROJEKTRA) |
| Priority | Must |
| Estimate | 4 h (build 2, tests 1, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0963 |
| Modules | invoicing, taxpack-ba |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As PROJEKTRA's controller, I want to mark an invoice as "issued via external fiscal system" with its fiscal reference, so that we stay compliant until a fiscalization adapter exists.

```gherkin
Scenario: Fiscal reference
  When the controller enters the fiscal reference "FR-TEST-0001" on a finalized invoice
  Then the reference is stored, shown on the invoice view and audited
  And it can be entered once and corrected only with a logged reason
```

---

### S-0966 Payments and open items (basic)

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0963 |
| Modules | invoicing |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want to record full and partial payments and see open items with due dates, so that I know who still owes us money and licenses can activate on payment.

```gherkin
Scenario: Partial and full payment
  Given INV-2026-0042 over 1,190.00 EUR
  When a payment of 500.00 EUR is recorded
  Then the status is "partially paid" with 690.00 EUR open
  When the remaining 690.00 EUR is recorded
  Then the status is "paid" and the event "invoice.paid" is emitted

Scenario: Open items
  Then a list shows open invoices with due date and days overdue
```

---

### S-0967 Down payment and final invoices

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Should |
| Estimate | 12 h (build 5, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0963, S-0966 |
| Modules | invoicing |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want down payment invoices and a final invoice that deducts them with correct tax, so that fixed-price projects can be billed in stages.

```gherkin
Scenario: Final invoice deducts down payments
  Given two paid down payment invoices of 1,000.00 EUR net each at 19 %
  When the final invoice over 5,000.00 EUR net is finalized
  Then both down payments are deducted with their VAT and the remaining amount due is 3,570.00 EUR gross
```
