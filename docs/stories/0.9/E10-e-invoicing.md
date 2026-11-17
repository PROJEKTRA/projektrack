# E10 E-invoicing (create, validate, send, archive)

Epic goal: every invoice can be issued as ZUGFeRD/Factur-X or XRechnung from the normal invoice form, validated before sending, sent by email and archived unchanged. Receiving incoming e-invoices is planned for 0.10.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0968 | ZUGFeRD/Factur-X generation (EN 16931 profile) | Must | 16 | L3 |
| S-0969 | XRechnung (CII and UBL) with inline mandatory field hints | Must | 12 | L3 |
| S-0970 | Validation against EN 16931 and XRechnung rules | Must | 14 | L3 |
| S-0971 | Send invoices by email with per-language templates | Must | 8 | L3 |
| S-0972 | Archive originals with hash and retention | Must | 6 | L3 |

Epic total: Must 56 h.

---

### S-0968 ZUGFeRD/Factur-X generation (EN 16931 profile)

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 16 h (build 8, tests 5, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0963, S-0964, ADR on e-invoicing library |
| Modules | einvoicing |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want any invoice issued as ZUGFeRD/Factur-X from the normal invoice form, so that clients can process it automatically without me knowing XML.

```gherkin
Scenario: ZUGFeRD from the normal form
  Given a client with preferred format "ZUGFeRD"
  When the controller finalizes an invoice
  Then a PDF/A-3 with embedded CII XML in profile EN 16931 is produced
  And seller and buyer data, VAT IDs, tax category codes per line, payment terms and bank details are filled automatically

Scenario: Reverse charge category
  Given a reverse charge invoice to an Austrian business
  Then the XML uses VAT category code "AE" with 0 % and the exemption reason text

Scenario: Works on AMP
  Then generation runs in pure PHP without Docker or external services
```

---

### S-0969 XRechnung (CII and UBL) with inline mandatory field hints

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must (simplification candidate: CII only in 0.9) |
| Estimate | 12 h (build 6, tests 4, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0968, S-0939 |
| Modules | einvoicing |
| Edition | Community |
| Reviewers | finance-tax, ux, qa |

**Story:** As a controller, I want XRechnung for public-sector clients with missing mandatory data pointed out inline, so that the invoice is accepted the first time.

```gherkin
Scenario: E-invoice to a German public-sector customer
  Given a client marked as public sector with preferred format "XRechnung"
  And the client has no Leitweg-ID yet
  When the controller tries to finalize the invoice
  Then the system points out the missing Leitweg-ID inline
  When the Leitweg-ID is entered and the invoice is finalized
  Then a valid XRechnung is generated, validated against EN 16931 without errors
  And it can be sent by email from Projektrack in one step

Scenario: Syntax choice
  Then XRechnung can be produced in CII and UBL syntax, configurable per client
```

---

### S-0970 Validation against EN 16931 and XRechnung rules

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 14 h (build 7, tests 5, review and docs 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0968, S-0969 |
| Modules | einvoicing |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want every e-invoice validated before it is sent, with readable errors in my language, so that I never send an invalid e-invoice.

```gherkin
Scenario: Validation before sending
  When an e-invoice is finalized
  Then it is validated with XSD and Schematron rules for EN 16931 (and XRechnung where applicable) in PHP
  And if the optional KoSIT validator service is configured, it is used in addition

Scenario: Readable errors
  Given an invoice line without a tax category
  Then the error list says in the user's language which line and field is wrong and how to fix it
  And sending is blocked until the error is resolved

Scenario: Reference test set
  Then CI validates a reference set covering domestic VAT, mixed rates, reverse charge, non-EU, small business, credit note and cancellation
```

---

### S-0971 Send invoices by email with per-language templates

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 8 h (build 4, tests 3, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0964, S-0970, S-0912 |
| Modules | einvoicing, invoicing |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As a controller, I want to send an invoice by email in one step with the right attachments and text, so that billing does not require another tool.

```gherkin
Scenario: One-step sending
  When the controller clicks "Send invoice"
  Then the email uses the template in the client's document language
  And attaches the PDF (ZUGFeRD) or the XML and PDF (XRechnung) as configured
  And the send event with recipient and timestamp is recorded on the invoice

Scenario: Client default
  Then each client has a default format (PDF, ZUGFeRD, XRechnung) and delivery channel (email, download)

Scenario: No tracking
  Then the email contains no tracking pixel and no rewritten links
```

---

### S-0972 Archive originals with hash and retention

| Field | Value |
|---|---|
| Persona | Auditor |
| Priority | Must |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0963, S-0960 |
| Modules | einvoicing |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As an auditor, I want every issued XML and PDF stored unchanged with a hash and retention period, so that GoBD requirements are met.

```gherkin
Scenario: Archive and verify
  Then the issued files are stored with SHA-256 hash, retention end date from the country pack and an audit trail
  And "projektrack archive:verify" reports any file whose hash does not match
  And files cannot be deleted before the retention end date
```
