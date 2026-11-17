# ADR-0008: E-invoicing (ZUGFeRD/Factur-X and XRechnung)

- **Status:** Accepted, 16.11.2026
- **Deciders:** Product owner, solution architecture, finance and tax review, QA
- **Related:** ADR-0001 (stack, pinned versions), ADR-0004 (money, tax cases, numbering, immutability), ADR-0005 (languages of documents and errors)

> **Legal disclaimer.** Statements about German and EU law in this ADR describe intended software behavior. Items marked **[accountant]** must be confirmed by a tax advisor before they are relied on. Sources checked on 16.11.2026 are linked; several are secondary (chambers of commerce, advisory firms) and must be checked against the official BMF letters.

## Context

Projektrack must create e-invoices "as easily as a normal invoice": every finalized invoice can be issued as ZUGFeRD/Factur-X (PDF/A-3 with embedded XML, profile EN 16931 by default) or XRechnung (UBL or CII syntax), validated before sending, sent by email, received and parsed (0.10), and archived. It must work on plain AMP shared hosting without Java.

### German e-invoicing timeline (verified 16.11.2026)

Legal basis: [§14 UStG](https://www.gesetze-im-internet.de/ustg_1980/__14.html) as amended by the Growth Opportunities Act, transitional rules in [§27 Abs. 38 UStG](https://www.gesetze-im-internet.de/ustg_1980/__27.html), BMF letters of 15.10.2024 and 15.10.2025 (second letter: [summary by Deloitte](https://www.deloitte-tax-news.de/steuern/indirekte-steuern-zoll/bmf-zweites-schreiben-zur-einfuehrung-der-verpflichtenden-e-rechnung-fuer-inlaendische-b2b-umsaetze.html), [IHK Köln](https://www.ihk.de/koeln/hauptnavigation/recht-steuern/steuern/bmf-schreiben-zur-e-rechnung-vom-15-10-2025-6774012), [Ebner Stolz](https://www.ebnerstolz.de/de/unser-angebot/leistungen/steuerberatung/umsatzsteuer/zweites-bmf-schreiben-e-rechnung-96456.html)).

| Date | Obligation for domestic B2B supplies between businesses established in Germany |
|---|---|
| Since 01.01.2025 | **Receiving:** every business must be able to receive and process e-invoices (no exception). |
| Until 31.12.2026 | Issuing: paper and other electronic formats (for example plain PDF, with recipient consent) still allowed. |
| From 01.01.2027 | **Issuing mandatory** for businesses with prior-year (2026) turnover above 800,000 EUR. Smaller businesses may still issue other formats until 31.12.2027. |
| From 01.01.2028 | **Issuing mandatory for all** businesses. EDI formats agreed between parties remain allowed until 31.12.2027 only if they allow extraction of the EN 16931 data. |
| Permanent exceptions | Small-amount invoices up to 250 EUR and tickets. |

Sources: [Grant Thornton](https://www.grantthornton.de/themen/2026/e-rechnungspflicht-2027-die-wichtigsten-fragen-und-antworten-zur-e-rechnung/), [IHK Stuttgart](https://www.ihk.de/stuttgart/fuer-unternehmen/recht-und-steuern/steuerrecht/steuermeldungen/e-rechnung-5864496), [Handelskammer Hamburg](https://www.handelskammer-hamburg.de/recht-steuern/steuerrecht/allgemeine-informationen/die-e-rechnungspflicht-fuer-unternehmen-6680082). **[accountant]** Some secondary sources word the end of the general transition differently; the BMF letter text is authoritative. A further extension by the legislator was discussed but not found enacted on 16.11.2026.

Relevance: German Projektrack customers (for example the pilot customer) need compliant e-invoice issuing by 01.01.2027 or 01.01.2028 and receiving now. PROJEKTRA itself, established in Bosnia and Herzegovina, is likely outside the domestic German mandate **[accountant]**, but German customers prefer e-invoices anyway.

### Format versions (verified 16.11.2026)

- **ZUGFeRD 2.5.2 / Factur-X 1.09.2** published 04.08.2026 ([FeRD downloads](https://www.ferd-net.de/en/downloads/publications), [FNFE-MPE Factur-X](https://fnfe-mpe.org/factur-x/factur-x_en/)). Previous: ZUGFeRD 2.4 / Factur-X 1.08 (04.12.2025, in force 15.01.2026).
- **XRechnung 3.0.x** is the production line (3.0.2); **XRechnung 4.0** is in pre-release, final expected in 2027; 3.0 stays valid until at least 31.07.2027 according to secondary sources ([vatupdate, 04.10.2026](https://www.vatupdate.com/2026/10/04/kosit-releases-xrechnung-4-0-pre-release-with-major-billing-updates/)). Official: [XStandards Einkauf / XRechnung](https://xeinkauf.de/xrechnung/), [e-rechnung-bund.de](https://e-rechnung-bund.de/).
- **KoSIT validator 1.6.3** (20.08.2026), XRechnung validator configuration release 2026-08-31 ([GitHub](https://github.com/itplr-kosit/validator), [configuration](https://github.com/itplr-kosit/validator-configuration-xrechnung/releases)); EN 16931 validation artifacts 1.3.17 (12.10.2026, [ConnectingEurope/eInvoicing-EN16931](https://github.com/ConnectingEurope/eInvoicing-EN16931/releases)).

## Decision

### 1. Architecture

```
Finalized invoice (ADR-0004, immutable)
        |
        v
EInvoiceMapper  ->  EInvoiceDocument (own canonical model, EN 16931 business terms BT/BG)
        |
        +--> CiiWriter (ZUGFeRD/Factur-X profiles, XRechnung CII)
        +--> UblWriter (XRechnung UBL)
        |
        v
Validator chain: XSD -> Projektrack rule set -> official Schematron (if a service is available)
        |
        v
PdfA3Builder (mPDF, embeds factur-x.xml)  |  XML file (XRechnung)
        |
        v
Archive (original bytes, SHA-256, audit) -> Send (email adapter, later portals and Peppol)
```

- The **canonical model** `EInvoiceDocument` is our own set of immutable value objects named after EN 16931 business terms. Libraries are used only behind `CiiWriter`, `UblWriter`, `EInvoiceReader` and `Validator` interfaces. This isolates us from library changes (see library maintenance below).
- **Formats offered per invoice:** PDF only, ZUGFeRD/Factur-X (profile EN 16931 by default; BASIC, EXTENDED and XRECHNUNG profiles configurable), XRechnung UBL (default for public-sector customers), XRechnung CII.
- **Default per customer:** preferred format and channel on the customer record; public-sector customers default to XRechnung UBL.
- **No separate workflow:** the format is chosen on the normal invoice form; missing mandatory data is flagged inline before finalization ("Leitweg-ID missing for this public customer").

### 2. Library choice

Checked on Packagist and GitHub on 16.11.2026:

| Library | Version, date | License | CII | UBL | Read | PDF/A-3 | Maintenance | Verdict |
|---|---|---|---|---|---|---|---|---|
| [horstoeko/zugferd](https://github.com/horstoeko/zugferd) | 1.0.135, 08.11.2026 | MIT | Yes, all profiles incl. XRechnung 1.x to 3.x | No | Yes (XML and PDF) | Merger via FPDF/FPDI | Very active (frequent releases); author names invoicesuite as official successor | **Chosen for CII writing and reading** |
| [horstoeko/zugferdublbridge](https://packagist.org/packages/horstoeko/zugferdublbridge) | 1.0.20, 01.11.2026 | MIT | Converter | CII to UBL and back | via conversion | n/a | Active | **Chosen for UBL output and UBL input** |
| [horstoeko/invoicesuite](https://packagist.org/packages/horstoeko/invoicesuite) | 0.0.42, 13.11.2026 (first release 04.2026) | MIT | Yes | Yes | Yes | uses mPDF and FPDI | Active, pre-1.0 | **Watch; migrate when 1.0 is released** |
| [easybill/zugferd-php](https://packagist.org/packages/easybill/zugferd-php) | 6.0.0, 30.03.2026 | MIT | Yes | No | Yes | No (XML only) | Moderate | Alternative for CII |
| [easybill/e-invoicing](https://packagist.org/packages/easybill/e-invoicing) | 0.4.0, 07.09.2026 | MIT | Yes | Yes | Yes | No | Active, pre-1.0 | Alternative |
| [num-num/ubl-invoice](https://packagist.org/packages/num-num/ubl-invoice) | 2.4.1, 09.02.2026 | MIT | No | Yes (UBL, Peppol BIS 3) | Partial | No | Moderate | Fallback for UBL writing and for Peppol later |
| [josemmo/einvoicing](https://packagist.org/packages/josemmo/einvoicing) | 0.3.1, 09.06.2025 | MIT | Yes | Yes | Yes | No | Low (no release for 17 months) | Rejected |
| [atgp/factur-x](https://packagist.org/packages/atgp/factur-x) | 3.5.0, 18.08.2026 | MIT | Embeds and extracts XML in PDF | No | Extract | Yes (FPDI based) | Moderate | Fallback for embedding |

Decision: **horstoeko/zugferd + horstoeko/zugferdublbridge**, pinned (ADR-0001), wrapped by our interfaces. A spike in sprint L3 checks whether horstoeko/zugferd already writes ZUGFeRD 2.5.x / Factur-X 1.09.x profile identifiers (**verify at scaffold**); if not, 2.4 / 1.08 output is used, which is still accepted by EN 16931 validators.

### 3. PDF/A-3 generation path on AMP

**Finding (verified in the mPDF source on 16.11.2026):** mPDF 8 **can** produce PDF/A-3 with an embedded XML file in pure PHP:

- `$mpdf->PDFA = true; $mpdf->PDFAversion = '3-B';` writes `pdfaid:part 3`, `pdfaid:conformance B` into XMP and an output intent with an embedded sRGB ICC profile.
- `$mpdf->SetAssociatedFiles([[ 'name' => 'factur-x.xml', 'mime' => 'text/xml', 'description' => 'Factur-X/ZUGFeRD invoice', 'AFRelationship' => 'Alternative', 'path' => $xmlPath ]]);` writes the file into `/EmbeddedFiles` and the catalog `/AF` array with the `AFRelationship`.
- `$mpdf->SetAdditionalXmpRdf($rdf)` adds the Factur-X XMP extension schema (`fx:DocumentType`, `fx:DocumentFileName`, `fx:Version`, `fx:ConformanceLevel`), which mPDF does not write by itself.

Source: [mPDF `Mpdf.php`](https://github.com/mpdf/mpdf/blob/development/src/Mpdf.php) and [`MetadataWriter.php`](https://github.com/mpdf/mpdf/blob/development/src/Writer/MetadataWriter.php) (methods `SetAssociatedFiles`, `SetAdditionalXmpRdf`, `PDFAversion` validation "Use: 1-B, 3-B"). The public documentation page for associated files is incomplete, so the behavior is pinned by our own tests.

Decision:

1. **Primary path (all profiles, AMP included):** render the invoice template (HTML, including RTL and Arabic shaping) with mPDF in PDF/A-3B mode, embed `factur-x.xml` (file name per Factur-X 1.0x / ZUGFeRD 2.1+) with `AFRelationship` `Alternative` (BASIC, EN 16931, EXTENDED, XRECHNUNG) or `Data` (MINIMUM, BASIC WL), as the Factur-X specification requires, add the Factur-X XMP extension. Single pass, no Java, no Ghostscript.
2. **Secondary path:** for PDFs that come from DOCX templates via Gotenberg (Docker profile), embed the XML into the finished PDF with horstoeko/zugferd's `ZugferdDocumentPdfMerger` (FPDF/FPDI). This path re-imports pages and must keep PDF/A-3 conformance; it is used only when the primary path cannot render the chosen template.
3. **PDF/A constraints enforced in templates:** all fonts embedded (mPDF does this), no transparency in logos (PNG with alpha is flattened at upload), no external resources, no JavaScript, no encryption.
4. **Verification in CI (not on customer servers):** every generated test PDF is checked with [veraPDF](https://verapdf.org/) (PDF/A-3B) and the XML with the KoSIT validator and the [Mustang](https://github.com/ZUGFeRD/mustangproject) validator (core 2.26.0). Both are Java tools used only in CI and in the Docker profile.

Risk: mPDF's PDF/A-3 output has not yet been validated by us against veraPDF with our templates. This is the first L3 task; if it fails, path 2 with FPDI or Gotenberg PDF/A conversion becomes the default for ZUGFeRD and the AMP profile falls back to path 2.

### 4. Validation on AMP vs. KoSIT service

PHP's ext-xsl supports only XSLT 1.0, while the official EN 16931 and XRechnung Schematron artifacts are compiled to XSLT 2.0. They cannot run natively on shared hosting.

Validation levels:

| Level | What | Where it runs | Always on |
|---|---|---|---|
| 1. Schema | XSD validation of CII (UN/CEFACT D16B as required by the profile) and UBL 2.1 with `DOMDocument::schemaValidate()`; schemas bundled | PHP, AMP | Yes |
| 2. Projektrack rule set | Pre-flight rules implemented in PHP against our canonical model and the generated XML: mandatory BTs per profile, totals (BR-CO-10 to BR-CO-17, BR-CO-15), VAT category rules (BR-S, BR-AE, BR-E, BR-O, BR-Z), XRechnung rules (BR-DE-1 to BR-DE-31 where data-based, for example BT-10 buyer reference, seller contact, electronic addresses), code lists (currency, country, unit, payment means, VATEX). Messages are string identifiers in the user's language | PHP, AMP | Yes |
| 3. Official Schematron | KoSIT validator 1.6.3 with the XRechnung configuration, as HTTP daemon (`java -jar ... -D`, Java 11+, Apache-2.0) | Docker profile, managed instances, PROJEKTRA instance | When configured |
| 3b. Pure-PHP Schematron | [invoicepress/vampire-php](https://packagist.org/packages/invoicepress/vampire-php) 1.3.0 (own XPath 2.0 subset interpreter, EN 16931 and XRechnung for UBL and CII) with [vampire-zugferd](https://packagist.org/packages/invoicepress/vampire-zugferd) 1.0.1 | PHP, AMP | **Evaluation only** |

Decision:

- Levels 1 and 2 are always active and block finalization or sending on errors.
- Level 3 is used automatically when `KOSIT_VALIDATOR_URL` is configured; it is bundled in the Docker Compose profile and enabled on all PROJEKTRA-managed instances, including the first customer's instance (C-03).
- The validation report states honestly which levels ran: "Validated: schema and Projektrack rules (n rules). Official KoSIT validation not available on this server." The admin documentation explains how to add the KoSIT service.
- Level 3b is evaluated in 0.10. Reasons for not adopting it now: first release 10.2026 (two versions), single maintainer, and GPL-2.0-or-later license, whose combination with the proprietary `/ee` distribution must be reviewed legally (it is compatible with AGPL-3.0 Community). If adopted, it would be an optional, separately installable Community module.
- Also considered for the Docker image: SaxonC-HE PHP extension to run the official XSLT 2.0 artifacts in-process (**verify at scaffold** for PHP 8.5 builds); not possible on shared hosting.
- The acceptance criterion "validated against EN 16931 without errors" is verified with level 3 in CI for every test invoice type and on managed instances in production.

### 5. EN 16931 business term mapping (overview)

| BT / BG | Term | Projektrack source | Notes |
|---|---|---|---|
| BT-1 | Invoice number | Document number (ADR-0004) | Gapless |
| BT-2 | Issue date | `finalized_at` local date | |
| BT-3 | Invoice type code | 380 invoice, 381 credit note, 384 corrected invoice, 386 prepayment invoice | Cancellation invoice: 381 **[accountant]** or 384 |
| BT-5 | Currency | Document currency | VAT accounting currency BT-6 when it differs (BA pack) |
| BT-9 | Due date | Payment terms | |
| BT-10 | Buyer reference | **Leitweg-ID** for public sector; otherwise customer reference | Mandatory in XRechnung (BR-DE-15) |
| BT-12 / BT-13 | Contract reference / purchase order reference | Contract number, customer PO | |
| BT-20 | Payment terms text | Payment terms string (document language) | |
| BT-22 / BG-1 | Invoice note | Legal notices (ADR-0004), free text | Reverse charge notice also here |
| BT-27 to BT-40 | Seller name, IDs, address | Tenant legal data | BT-31 VAT ID, BT-32 tax number |
| BT-34 | Seller electronic address | Tenant invoice email or Peppol ID | Mandatory in XRechnung 3.0 |
| BG-6 (BT-41 to BT-43) | Seller contact | Finance contact of tenant | Mandatory in XRechnung (BR-DE-2, -5, -6, -7) |
| BT-44 to BT-55 | Buyer name, VAT ID (BT-48), address | Customer record | BT-48 mandatory for reverse charge (BR-AE-02) |
| BT-49 | Buyer electronic address | Customer e-invoice address | Mandatory in XRechnung 3.0 |
| BT-72 / BG-14 | Delivery date / invoicing period | Service date or service report period | Required by §14 Abs. 4 UStG |
| BG-16 (BT-81 to BT-86) | Payment instructions | Means code 58 (SEPA credit transfer) or 30, IBAN, BIC | Tenant bank account |
| BG-22 (BT-106 to BT-115) | Document totals | ADR-0004 totals | BR-CO-10 to BR-CO-16 |
| BG-23 (BT-116 to BT-121) | VAT breakdown | Rate groups from the tax engine | Category code (S, AE, E, O, Z), exemption reason text and VATEX code |
| BG-24 (BT-122 to BT-125) | Additional supporting documents | Service report PDF attached as base64 | Optional, useful for time and material invoices |
| BG-25 (BT-126 to BT-161) | Invoice lines | Lines with quantity (BT-129), unit code (BT-130: HUR for hours, C62 for pieces, DAY), net amount (BT-131), net price (BT-146), VAT category (BT-151), rate (BT-152), item name (BT-153) | Line period BG-26 from time entries |

The full mapping table with every BT, cardinality per profile and test fixture lives in `docs/architecture/einvoice-mapping.md` (created with the implementation).

### 6. Leitweg-ID

- Field `leitweg_id` on the customer (and override on the contract or invoice), required when the customer is flagged `public_sector` and the chosen format is XRechnung.
- Format per the KoSIT Leitweg-ID specification ([XStandards Einkauf](https://xeinkauf.de/)): coarse address (2 to 12 digits) `-` optional fine address (up to 30 alphanumeric characters) `-` check digits (2 digits, ISO 7064 MOD 97-10). Shape illustration (synthetic, check digits not computed): `04011000-12345-XX`. Syntax and check digits are validated inline; **verify the exact length rules against the current specification at scaffold**.
- Mapped to BT-10. Missing Leitweg-ID blocks finalization of XRechnung invoices to public-sector customers with an inline message.

### 7. Sending by email

- From the invoice view: "Send" opens a prefilled email from a per-language template (ADR-0005); recipient from the customer's invoice contact or e-invoice address.
- Attachments: ZUGFeRD: one PDF/A-3 file. XRechnung: the XML file (the legally relevant invoice) plus, optionally, a human-readable PDF clearly marked as a visualization. Additional supporting documents (service report) can be embedded in the XML (BG-24) or attached.
- Sent emails are logged (time, recipient, message id, attachment hashes) on the invoice; bounces are captured where the mail transport reports them.
- Transmission adapter interface `EInvoiceChannel` (`email`, later `zre`/`ozg-re` public-sector portals, `peppol`) with per-customer default channel.

### 8. Receiving and parsing (0.10, Business edition)

- Inputs: drag-and-drop upload and a dedicated inbound mailbox per tenant (IMAP polling via cron on AMP).
- Detection: XML root `rsm:CrossIndustryInvoice` (CII) or `Invoice`/`CreditNote` (UBL); PDF with embedded `factur-x.xml`, `zugferd-invoice.xml` or `xrechnung.xml`.
- Parsing: CII via horstoeko/zugferd reader; UBL via zugferdublbridge conversion to CII, then the same reader; into the canonical model.
- Validation of incoming invoices with the same levels (results shown, never blocking receipt).
- Readable invoice view in our UI (in the user's language), not dependent on the KoSIT XSLT visualization (which requires Java).
- Proposals: supplier by VAT ID, IBAN or name; project and work package by order reference or contract reference; cost category (for example subcontractor costs). After confirmation the costs flow into project controlling.
- Duplicate detection by seller VAT ID + invoice number + SHA-256 of the original file.

### 9. Archive and GoBD

- The original file (PDF/A-3 or XML, issued or received) is stored **unchanged** in write-once application storage with SHA-256, size, MIME type, timestamps and a link in the tamper-evident audit hash chain.
- For hybrid formats the XML data are the legally leading part according to the BMF letters **[accountant]**; both the PDF and the extracted XML are archived.
- Retention per country pack: DE 8 years for invoices issued from 2025 ([§14b UStG](https://www.gesetze-im-internet.de/ustg_1980/__14b.html), [§147 AO](https://www.gesetze-im-internet.de/ao_1977/__147.html)) **[accountant]**; deletion after retention only through a documented, logged process, never automatic for finalized invoices without admin confirmation.
- GoBD principles (completeness, immutability, traceability, machine readability, availability) are supported by the data model; a template for the customer's procedure documentation (Verfahrensdokumentation) is provided in the admin docs. Backups include the archive and are verified by hash.

### 10. Peppol later

Peppol BIS Billing 3.0 is planned for 2.0 (preparation in 1.3) through an access point adapter behind `EInvoiceChannel`; UBL output from section 2 is the basis. No Peppol access point is operated by Projektrack itself.

## Alternatives considered

- **Mandatory KoSIT validator (Java) for all installations:** violates the AMP requirement. Rejected; optional service instead.
- **Ghostscript for PDF/A-3 conversion:** not available on most shared hosts; adds a binary dependency. Rejected for AMP; may be used in the Docker image if veraPDF tests require it.
- **Gotenberg PDF/A conversion as the only path:** requires Docker. Rejected as only path; used for DOCX-based templates.
- **TCPDF / tc-lib-pdf for the invoice PDF:** capable of PDF/A, but weaker HTML/CSS and RTL template support than mPDF for our document types. Rejected.
- **Own XML serializer without libraries:** high effort, error-prone for profiles and code lists. Rejected; our canonical model plus maintained libraries.
- **horstoeko/invoicesuite now:** pre-1.0 and started in 04.2026. Rejected for 0.9, planned migration when stable.
- **Hosted validation APIs:** send invoice data to a third party; conflicts with privacy and invisibility rules. Rejected.

## Consequences

**Positive**
- ZUGFeRD and XRechnung creation works on shared hosting in pure PHP.
- Validation is honest about its depth; managed instances get full official validation.
- Library choices are isolated behind our interfaces.

**Negative and risks**
- **PDF/A-3 conformance of mPDF output is unproven for our templates** until veraPDF tests pass (first L3 task, fallback defined).
- **Pure-PHP validation is not equivalent to the official Schematron.** On AMP without the KoSIT service, an invoice can pass our checks and still fail a recipient's official validator. Mitigation: rule set derived from the official rules with shared test fixtures; clear status label.
- **Library maintenance:** horstoeko/zugferd is very active but its author steers new work to invoicesuite (pre-1.0). Format updates (ZUGFeRD 2.5.x, XRechnung 4.0 in 2027) need library releases or our own patches.
- **Format churn:** XRechnung 4.0 and the EU ViDA reform will require updates in 2027; the canonical model keeps the impact local.

## Open questions

1. **[accountant]** Whether PROJEKTRA (seller established in BiH) falls under the German issuing obligation for invoices to German businesses, and which VAT category code to use (AE vs. O, see ADR-0004).
2. **[accountant]** Invoice type code for cancellation invoices (381 credit note vs. 384 corrected invoice) in Germany.
3. **[accountant]** Confirm the timeline above against the BMF letters, especially the end of the general transition (31.12.2026) and the 800,000 EUR threshold year.
4. **Product owner:** default ZUGFeRD profile EN 16931 (proposal) vs. XRECHNUNG profile for German B2B customers.
5. **Product owner:** whether to bundle the KoSIT validator in the AMP ZIP as an optional download (requires Java on the host, rare on shared hosting).
6. **Legal:** license compatibility review for GPL-2.0-or-later validation libraries in a distribution that also contains proprietary `/ee` modules.
