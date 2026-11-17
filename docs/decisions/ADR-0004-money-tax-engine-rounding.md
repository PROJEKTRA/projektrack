# ADR-0004: Money, tax engine and rounding

- **Status:** Accepted, 16.11.2026
- **Deciders:** Product owner, solution architecture, finance and tax review, QA
- **Related:** ADR-0001 (stack), ADR-0003 (license sales), ADR-0005 (legal notices per language), ADR-0008 (e-invoicing)

> **Legal disclaimer.** Statements about tax law in this ADR describe the intended software behavior. Every statement marked **[accountant]** must be confirmed by PROJEKTRA's accountant (and, for German customers, by a German tax advisor) before the first real invoice is issued (assumption A-04). The software ships country packs as configurable data so that corrections do not need code changes.

## Context

Projektrack calculates values for time entries, service reports, quotes and invoices in several currencies (EUR, BAM, USD and others) and under several tax regimes. Release 0.9 must issue legally correct invoices for:

- German sellers: domestic VAT 19 % and 7 %, EU B2B reverse charge, non-EU services, small business exemption, exempt services.
- PROJEKTRA itself (Bosnia and Herzegovina seller): domestic PDV 17 %, services to foreign business customers (for example Germany).

Hard rules: no floats for money; finalized invoices are immutable; numbering is gapless; rate changes are never retroactive; time is stored in minutes.

## Decision

### 1. Money representation

- Library: [brick/money](https://github.com/brick/money) 0.15.x (ADR-0001), on top of brick/math.
- **Stored amounts:** integer minor units (`BIGINT`) plus ISO 4217 currency code (`CHAR(3)`) in two columns, for example `net_amount_minor = 136248`, `currency = 'EUR'`. Never `FLOAT`/`DOUBLE`; `DECIMAL` is used only for non-monetary decimals.
- **Unit prices and rates** may carry more precision than the currency: stored as `DECIMAL(19,4)` and handled as `BigDecimal`. Hourly rates normally have 2 decimals.
- **Quantities:** time as integer minutes; other quantities as `DECIMAL(18,4)`.
- **Tax rates:** integer hundredths of a percent (`1900` = 19.00 %, `700` = 7.00 %, `1700` = 17.00 %).
- **Intermediate calculations** use `RationalMoney` or `BigDecimal` without rounding. Rounding happens only at the defined rounding points below.
- **Currencies with other minor units** (JPY 0, KWD 3) work through brick/money's currency metadata; no code assumes 2 decimals.

### 2. Rounding strategy

**Default rounding mode: `HALF_UP`** (commercial rounding, 0.125 becomes 0.13; -0.125 becomes -0.13, away from zero). A country pack may define another mode; none of the 0.9 packs does.

**Rounding points (default "per rate group", used by DE, BA and EU generic packs):**

1. **Line net:** `line_net = round(quantity x unit_price, currency minor units)`. For time lines: `round(minutes x hourly_rate / 60)`. Line discounts are applied before this rounding.
2. **Document discount** (if any) is allocated to rate groups proportionally to their net, truncated to minor units per group, with the residual minor units assigned to the groups with the largest remainders (largest remainder method, ties to the larger group), so that groups sum exactly to the discount.
3. **VAT per rate group:** `vat_group = round(sum(line_net in group) x rate)`. One rounding per tax rate and category.
4. **Totals:** `total_net = sum(line_net)`, `total_vat = sum(vat_group)`, `total_gross = total_net + total_vat`. No further rounding.

This matches EN 16931 rule BR-CO-17 (VAT category tax amount = taxable amount x rate, rounded to two decimals), so the same numbers go into the PDF, the ZUGFeRD/XRechnung XML and the DATEV export.

**Alternative mode "per line" (`vat_rounding = per_line`):** VAT is rounded per line and summed. Supported by the engine for country packs or customers that require it, documented per country pack, but **not allowed for documents issued as EN 16931 e-invoices**, because it can diverge from BR-CO-17 by a few cents (see test T2). The invoice form blocks the combination and explains why.

**Gross price entry (B2C, later):** prices entered gross are converted to net per rate group with the same rounding points; the gross total stays exactly as entered. Not used in 0.9 (B2B only).

**Time rounding** (for example "round up to 15 minutes") is applied to minutes before valuation and is a separate rule from money rounding (tenant, customer or contract setting).

### 3. Currency conversion

- Every document has one document currency. Amounts from time entries or price lists in another currency are converted with a **stored exchange rate**.
- `exchange_rates` table: `from_currency`, `to_currency`, `rate` as `DECIMAL(20,10)`, `rate_date`, `source` (`fixed`, `manual`, `import:<provider>`), `created_by`.
- **BAM/EUR is fixed: 1 EUR = 1.95583 BAM** (currency board of the [Central Bank of Bosnia and Herzegovina](https://www.cbbh.ba/)), seeded with `source = fixed` and not editable (assumption A-10). Other pairs are entered manually in 0.9; an import adapter (ECB, CBBH daily list) follows later.
- The rate used, its source and its date are **copied onto the document line or document** when it is created (booking date for time valuation, invoice date for invoice conversion; configurable per country pack). Later rate changes never alter existing documents.
- Conversion rounds once, `HALF_UP`, to the target currency's minor units.
- Where a country pack requires VAT amounts in the local currency on foreign-currency invoices, the document shows the converted VAT amount and the rate used (BA pack: **[accountant]** whether PDV amounts must also be shown in BAM on EUR invoices).

### 4. Tax engine: pure domain layer

The tax engine is a framework-free PHP package (`app/Domain/Tax`, no Eloquent, no facades, no I/O). It receives a value object and returns a decision. Country packs supply rules and data through interfaces. This makes it fully unit-testable and reusable for quotes, invoices, credit notes and e-invoice export.

**Input: `TaxContext`**

| Field | Example | Notes |
|---|---|---|
| `seller.country` | `DE`, `BA` | From tenant legal data |
| `seller.vat_status` | `registered`, `small_business`, `not_registered` | Tenant setting, with validity dates |
| `seller.vat_id`, `seller.tax_number` | `DE123456789` | |
| `customer.country` | `AT` | Billing address country |
| `customer.tax_status` | `domestic`, `eu_business`, `non_eu_business`, `private`, `exempt`, `public_sector` | CRM field |
| `customer.vat_id`, `customer.vat_id_validated_at` | `ATU12345678`, VIES check timestamp | VIES check optional |
| `supply.type` | `service_general`, `service_land`, `service_event`, `service_electronic`, `goods`, `license_software` | From line item or service category |
| `supply.place_override` | null | Only via manual override |
| `supply.date` / `supply.period` | `2026-11-30` | Service date or period; determines rates and rules in force |
| `line.rate_class` | `standard`, `reduced`, `zero`, `exempt:<reason>` | From product or service category |

**Output: `TaxDecision`** (per line, aggregated per document)

| Field | Example |
|---|---|
| `case` | `EU_B2B_REVERSE_CHARGE` |
| `rate_ppm` | `0` (tax rates are stored in parts per million, e.g. 19 % = 190000, 8.875 % = 88750; see data model) |
| `category_code` | `AE` (UNTDID 5305, used by EN 16931) |
| `exemption_reason_code` | `VATEX-EU-AE` |
| `reason_string` | `tax:reason_eu_b2b_reverse_charge` (translatable, with ICU arguments) |
| `legal_notice_strings` | `["tax:notice_reverse_charge"]` |
| `required_fields` | `["customer.vat_id", "seller.vat_id"]` |
| `place_of_supply` | `AT` |
| `rule_ref` | `DE.2026.rc.01` (country pack rule id and version, stored for audit) |

The decision is shown to the user with its reason ("Reverse charge because the customer is an EU business with a valid VAT ID and the service follows the general B2B rule"). The decision and `rule_ref` are frozen on the finalized document.

### 5. Tax cases

| Case id | Description | Rate | EN 16931 category | Legal basis (to be confirmed) |
|---|---|---|---|---|
| `DE_DOMESTIC_STANDARD` | German domestic supply, standard rate | 19 % | S | [§12 Abs. 1 UStG](https://www.gesetze-im-internet.de/ustg_1980/__12.html) |
| `DE_DOMESTIC_REDUCED` | German domestic supply, reduced rate | 7 % | S | [§12 Abs. 2 UStG](https://www.gesetze-im-internet.de/ustg_1980/__12.html) |
| `EU_B2B_REVERSE_CHARGE` | Service to a business in another EU member state, general rule; recipient liable | 0 % | AE | [Art. 44 and Art. 196 Directive 2006/112/EC](https://eur-lex.europa.eu/eli/dir/2006/112/oj); [§3a Abs. 2 UStG](https://www.gesetze-im-internet.de/ustg_1980/__3a.html), [§13b UStG](https://www.gesetze-im-internet.de/ustg_1980/__13b.html), notice per [§14a Abs. 5 UStG](https://www.gesetze-im-internet.de/ustg_1980/__14a.html) |
| `NON_EU_B2B_OUTSIDE_SCOPE` | Service by an EU seller to a business outside the EU; place of supply abroad; not taxable in the seller's country | 0 % | O **[accountant]** | Art. 44 Directive 2006/112/EC; §3a Abs. 2 UStG |
| `EU_B2C_SELLER_COUNTRY` | Service to a private person, general rule: place of supply at seller | seller rate | S | Art. 45 Directive 2006/112/EC |
| `NON_EU_B2C_ART59` | Catalog services (consulting, IT, data processing) to private persons outside the EU: place of supply at customer | 0 % | O **[accountant]** | Art. 59 Directive 2006/112/EC; §3a Abs. 4 UStG |
| `DE_SMALL_BUSINESS` | Seller uses the small business scheme; no VAT charged | 0 % | E | [§19 UStG](https://www.gesetze-im-internet.de/ustg_1980/__19.html) (since 01.01.2025: prior year up to 25,000 EUR, current year up to 100,000 EUR) **[accountant]** |
| `EXEMPT` | Exempt supply with reason (for example certain training or medical services) | 0 % | E | [§4 UStG](https://www.gesetze-im-internet.de/ustg_1980/__4.html), reason per line |
| `BA_DOMESTIC_STANDARD` | BiH domestic supply by a PDV-registered seller | 17 % | S | [Zakon o porezu na dodanu vrijednost BiH](https://www.uino.gov.ba/) (consolidated text e.g. [paragraf.ba](https://www.paragraf.ba/propisi/bih/zakon-o-porezu-na-dodatu-vrijednost.html)) **[accountant]** |
| `BA_EXPORT_SERVICE_B2B` | Service by a BiH seller to a foreign business; place of supply at the recipient's seat; not subject to BiH PDV | 0 % | AE if the customer is in the EU and liable under its law, otherwise O **[accountant]** | BiH VAT Act Art. 15 (place of supply of services) **[accountant]**; for German customers the recipient is liable under §13b Abs. 2 Nr. 1 and Abs. 5 UStG |
| `BA_NOT_REGISTERED` | BiH seller not in the PDV system | 0 % | O | BiH VAT Act registration threshold **[accountant]** |
| `US_SALES_TAX` | US sales tax per jurisdiction (state, county, city), taxable vs. non-taxable items, exemption certificates | per jurisdiction | n/a | 1.0, US country pack |
| `MANUAL_OVERRIDE` | Any case chosen by a user with permission and justification | as chosen | as chosen | Audit log |

Special place-of-supply rules (land-related services, admission to events, passenger transport, electronic services to EU consumers under OSS) are detected via `supply.type` and, in 0.9, return a decision `MANUAL_REVIEW_REQUIRED` that blocks finalization until a user with permission chooses a case with justification. OSS support is out of scope for 0.9.

### 6. Decision table (0.9)

Evaluated top to bottom; first matching row wins. "Valid VAT ID" means a syntactically valid VAT ID is present; VIES validation is optional and its result and timestamp are stored when used.

| # | Seller | Seller status | Customer location | Customer status | Supply type | Result |
|---|---|---|---|---|---|---|
| 1 | any | any | any | any | special place-of-supply type | `MANUAL_REVIEW_REQUIRED` |
| 2 | DE | small_business | any | any | any | `DE_SMALL_BUSINESS` |
| 3 | DE | registered | any | any | line rate class `exempt:*` | `EXEMPT` with reason |
| 4 | DE | registered | DE | any | general | `DE_DOMESTIC_STANDARD` or `DE_DOMESTIC_REDUCED` by line rate class |
| 5 | DE | registered | EU other | business, valid VAT ID | general | `EU_B2B_REVERSE_CHARGE` |
| 6 | DE | registered | EU other | business, VAT ID missing | general | Blocked: "VAT ID missing"; user adds VAT ID or overrides with justification |
| 7 | DE | registered | EU other | private | general | `EU_B2C_SELLER_COUNTRY` (19 %/7 %) |
| 8 | DE | registered | non-EU | business | general | `NON_EU_B2B_OUTSIDE_SCOPE` |
| 9 | DE | registered | non-EU | private | catalog service (Art. 59) | `NON_EU_B2C_ART59` |
| 10 | DE | registered | non-EU | private | other general | `EU_B2C_SELLER_COUNTRY` |
| 11 | BA | not_registered | any | any | any | `BA_NOT_REGISTERED` |
| 12 | BA | registered | BA | any | general | `BA_DOMESTIC_STANDARD` |
| 13 | BA | registered | outside BA | business | general | `BA_EXPORT_SERVICE_B2B` |
| 14 | BA | registered | outside BA | private | general | `BA_DOMESTIC_STANDARD` **[accountant]** |
| 15 | other EU (generic pack) | registered | same as seller | any | general | `<CC>_DOMESTIC_STANDARD` from pack rates |
| 16 | other EU (generic pack) | registered | EU other / non-EU | business | general | rows 5 / 8 analogously |

The engine never silently falls through: if no row matches, the result is `MANUAL_REVIEW_REQUIRED`.

### 7. Country packs

A country pack is a module (`modules/TaxPackDE`, `modules/TaxPackBA`, `modules/TaxPackEU`) containing versioned data and a small rule class:

- **Rates** with validity periods (`valid_from`, `valid_to`) per rate class; the rate in force on `supply.date` is used. Rate changes are added as new rows, never edited in place once used.
- **Mandatory invoice fields** (for DE per [§14 Abs. 4 UStG](https://www.gesetze-im-internet.de/ustg_1980/__14.html), for example service date or period, seller tax number or VAT ID, customer VAT ID for reverse charge), checked inline before finalization.
- **Legal notices** as string identifiers (ADR-0005), with the language taken from the document language.
- **Numbering defaults** (`{PREFIX}-{YYYY}-{####}`, yearly reset; assumption A-09).
- **Retention periods:** DE 8 years for invoices issued from 2025 ([§14b UStG](https://www.gesetze-im-internet.de/ustg_1980/__14b.html), [§147 AO](https://www.gesetze-im-internet.de/ao_1977/__147.html) as amended by the Fourth Bureaucracy Relief Act) **[accountant]**; BA: **[accountant]**.
- **Rounding mode and VAT rounding level** (section 2).
- **Fiscalization requirement flag** and adapter key (BA: invoices may be marked "issued via external fiscal system" with a fiscal reference until an adapter exists; assumption A-05).
- **EU generic pack:** EU rules (rows 5, 7, 8, 9) plus a rate table for all member states, maintained as data; used for sellers in EU countries without a dedicated pack.

### 8. Legal notices per language

Legal notices are translatable strings with a `legal` flag. Draft wording (all **[accountant]**):

| String id | en | de | bs |
|---|---|---|---|
| `tax:notice_reverse_charge` | Reverse charge: the recipient of the service is liable for VAT (Art. 196 Council Directive 2006/112/EC). | Steuerschuldnerschaft des Leistungsempfängers. | Prijenos porezne obaveze: PDV obračunava primalac usluge (čl. 196 Direktive Vijeća 2006/112/EZ). |
| `tax:notice_outside_scope_de` | Not taxable in Germany: the place of supply is outside Germany. | Nicht im Inland steuerbare Leistung. | Usluga nije oporeziva u Njemačkoj: mjesto prometa je izvan Njemačke. |
| `tax:notice_small_business_de` | No VAT is charged under the small business scheme (§19 UStG). | Gemäß § 19 UStG wird keine Umsatzsteuer berechnet. | PDV se ne obračunava u skladu s članom 19 njemačkog Zakona o PDV-u (mali poduzetnici). |
| `tax:notice_ba_export_service` | Not subject to BiH VAT: the place of supply of the service is outside Bosnia and Herzegovina (Art. 15 BiH VAT Act). | Nicht der Mehrwertsteuer in Bosnien und Herzegowina unterliegend: Ort der Leistung außerhalb von Bosnien und Herzegowina. | Promet usluga nije predmet oporezivanja PDV-om u BiH: mjesto prometa usluge je izvan Bosne i Hercegovine (čl. 15 Zakona o PDV-u). |

- For the German reverse-charge notice the German wording of §14a Abs. 5 UStG is shown in every language version as a second line when the place of supply is Germany, so the legally relevant phrase is always present.
- Admins may override legal strings only with the permission `tax.legal_notice.override`; every change is audit-logged and shown with a "modified legal notice" marker in the country pack screen.

### 9. Override with permission and justification

- Permission `invoices.tax_override`. The user picks another case from the list (never free rates outside the country pack) and must enter a justification (minimum 20 characters).
- The original decision, the chosen case, the justification, the user and the timestamp are stored on the document and in the audit log. The document shows no trace of the override to the customer, but internal views show a badge "tax case overridden".
- Overrides are reported in the controller dashboard.

### 10. Gapless numbering with DB-agnostic locking

- **Drafts have no number.** A number is assigned only in the finalization transaction.
- Table `number_sequences(id, tenant_id, document_type, series_key, period_key, next_value, format, reset_policy)`, one row per tenant, document type, series and period (for example `2026`).
- Finalization runs in one database transaction:
  1. `SELECT ... FROM number_sequences WHERE ... FOR UPDATE` (Laravel `lockForUpdate()`; row-level lock supported identically on MySQL 8.4+/9.7 InnoDB, MariaDB 10.11+ InnoDB and PostgreSQL 16+). If the row does not exist it is created with `INSERT` and the unique key `(tenant_id, document_type, series_key, period_key)`; a duplicate-key race is caught and the select is retried.
  2. Format the number, increment `next_value`.
  3. Set document number, status `finalized`, `finalized_at`, content hash; write audit record.
  4. Commit. On any error the whole transaction rolls back, including the increment, so no gap is created.
- A unique index `(tenant_id, document_type, number)` on documents is the safety net.
- Deadlocks and lock timeouts are retried up to 3 times with jitter. No Redis or application-level locks are used for financial numbering, so the same code works on the AMP profile.
- Rendering of PDF and XML happens **after** commit in a queued job, so slow rendering does not hold the lock; the document is already immutable and numbered.
- A nightly job checks each sequence for gaps and duplicates and raises an alert (never auto-fixes).
- Period key uses the tenant time zone date of finalization.

### 11. Immutability and cancellation

- After finalization, invoice header, lines, tax decisions, totals, rates and texts are immutable. Enforcement in three layers: domain model (no mutators in `Finalized` state), Eloquent `updating`/`deleting` guards that throw, and a content hash (SHA-256 over the canonical JSON of the document) stored at finalization and verified when rendering and archiving.
- Allowed changes after finalization: payment status, dunning level, sent status, internal notes, attachments added (never replaced).
- **Cancellation:** a cancellation invoice (or credit memo in the US pack) is a new document with its own number from the configured series, referencing the original, with all amounts negated using the original tax decisions and rates. The original gets status `cancelled` (a status change, not a content change). "Cancel and re-issue" creates the cancellation and a new draft prefilled from the original.
- Deletion of finalized documents is impossible at application level; database users of the application have no `DELETE` need on these tables in normal operation.

### 12. Rate hierarchy resolution (hourly rates)

Eight levels, most specific wins:

1. Individual entry (manual rate, permission `timesheets.set_rate`)
2. Work package
3. Contract
4. Project
5. Customer
6. Employee
7. Role
8. Tenant default

Each level holds price list entries `{scope_type, scope_id, rate_kind (sales|cost), currency, amount, valid_from, valid_to, employee_id?, role_id?}`. Within one level an entry may be generic or qualified by employee or by role.

**Algorithm** for a time entry (work date `d` in the tenant time zone, employee `e`, role `r` at date `d`, work package `wp`, contract `c`, project `p`, customer `k`, tenant `t`, rate kind `kind`):

```
if entry.manual_rate is set: return (manual_rate, source = "entry")
for level in [wp, c, p, k, e, r, t]:
    candidates = entries at level where kind matches
                 and valid_from <= d and (valid_to is null or d <= valid_to)
    within candidates, prefer in this order:
        qualified by employee e
        qualified by role r
        generic
    if exactly one best candidate: return (candidate, source = level)
    if more than one best candidate: configuration error, return error "ambiguous rate"
return error "no rate found" (entry stays unvalued, report shows a warning)
```

Rules:

- Validity periods within the same scope and qualifier must not overlap; the price list editor enforces this.
- **No retroactive change:** when a time entry is approved, the resolved rate, its source level, entry id, currency and validity are **snapshotted** onto the entry. Approved, reported or invoiced entries are never revalued by later price list changes. Unapproved entries are revalued on read until approval.
- Price list entries that were used in a snapshot cannot be edited, only ended (`valid_to`) and superseded by a new entry.
- Currency of the rate may differ from the contract currency; conversion follows section 3 with the rate at the work date.
- Surcharges (weekend, holiday, night, on-call) are applied after base rate resolution as percentage or fixed amount, also snapshotted.
- Sales and cost rates resolve independently with the same algorithm.

## Test cases (expected values)

All tests run on MySQL, MariaDB and PostgreSQL in CI. "Group" means rounding per rate group (default).

| # | Scenario | Input | Expected |
|---|---|---|---|
| T1 | DE domestic, three lines, mixed 19 % / 7 % | L1: 600 min x 100.00 EUR/h at 19 %; L2: 3 x 33.33 EUR at 19 %; L3: 1 x 49.99 EUR at 7 % | Lines: 1,000.00 / 99.99 / 49.99. Group 19 %: base 1,099.99, VAT 209.00 (208.9981). Group 7 %: base 49.99, VAT 3.50 (3.4993). Net 1,149.98, VAT 212.50, gross 1,362.48 |
| T2 | Per group vs. per line rounding | 3 lines x 1.50 EUR at 19 % | Group: VAT 0.86 (0.855), gross 5.36. Per-line mode: 3 x 0.29 = 0.87, gross 5.37. E-invoice output allowed only with group mode |
| T3 | Minutes to money | 20 min x 100.00 EUR/h; 435 min x 80.00 EUR/h | 33.33 EUR (33.333...); 580.00 EUR |
| T4 | HALF_UP | 0.125 EUR rounded to cents; -0.125 EUR | 0.13; -0.13 |
| T5 | Document discount allocation | T1 with document discount 50.00 EUR | Allocation: 19 % group 47.83, 7 % group 2.17 (sum 50.00). 19 % base 1,052.16, VAT 199.91 (199.9104); 7 % base 47.82, VAT 3.35 (3.3474). Net 1,099.98, VAT 203.26, gross 1,303.24 |
| T6 | BA domestic PDV 17 % | 1,500.00 BAM + 333.33 BAM | Base 1,833.33, PDV 311.67 (311.6661), gross 2,145.00; rendered in `bs` by default |
| T7 | EU B2B reverse charge (DE seller, AT business with VAT ID) | 40 h x 100.00 EUR consulting | Net 4,000.00, VAT 0.00, case `EU_B2B_REVERSE_CHARGE`, category AE, both VAT IDs present, notice in document language plus German notice line |
| T8 | Reverse charge without customer VAT ID | as T7, VAT ID empty | Finalization blocked with "VAT ID missing" |
| T9 | BA seller to DE business (PROJEKTRA case) | 40 h x 100.00 EUR, invoice in EUR, English | Net 4,000.00 EUR, PDV 0.00, case `BA_EXPORT_SERVICE_B2B`, BAM equivalent 7,823.32 BAM at 1.95583 (if shown), notice `tax:notice_ba_export_service` **[accountant]** |
| T10 | DE seller to US business | 2,500.00 EUR consulting | VAT 0.00, case `NON_EU_B2B_OUTSIDE_SCOPE`, notice `tax:notice_outside_scope_de` |
| T11 | §19 small business | 1,200.00 EUR | VAT 0.00, case `DE_SMALL_BUSINESS`, category E, notice present |
| T12 | Conversion EUR to BAM | 512.34 EUR at 1.95583 | 1,002.05 BAM (1,002.0499...) |
| T13 | Cancellation | Cancel finalized invoice from T1 (INV-2026-0042) | New document with its own next number, net -1,149.98, VAT -212.50 (groups -209.00 / -3.50), gross -1,362.48; original status `cancelled`; original content unchanged (hash equal) |
| T14 | Immutability | Edit a line of a finalized invoice | Rejected; "Cancel and re-issue" offered; sequence unchanged |
| T15 | Gapless under concurrency | 50 parallel finalizations in one sequence, 5 of them forced to fail after numbering | 45 documents numbered 1 to 45 without gaps or duplicates; failed ones stay drafts without number |
| T16 | Rate not retroactive | Senior IT Project Lead 100 EUR/h valid to 31.10.2026, 110 EUR/h from 01.11.2026; entries 31.10. and 01.11. (480 min each) | 800.00 EUR and 880.00 EUR |
| T17 | Rate date in tenant time zone | Entry on work date 31.10.2026 recorded at 23:30 Europe/Sarajevo (22:30 UTC) | 100 EUR/h (work date, not UTC timestamp, drives validity) |
| T18 | Rate hierarchy | Role rate 80, customer rate 95, contract rate for role 90, work package generic rate none | Contract rate 90 wins (level 3 before 5 and 7); source "contract" shown |
| T19 | Ambiguous rate | Two generic contract rates with overlapping validity | Price list editor rejects the second entry; if forced via import, resolution returns "ambiguous rate" |
| T20 | Snapshot after approval | Approve entry at 100 EUR/h, then change contract rate to 120 | Entry remains 100 EUR/h; service report and invoice use 100 |
| T21 | Tax override | Change case from `EU_B2B_REVERSE_CHARGE` to `DE_DOMESTIC_STANDARD` without justification; then with justification by a user without permission; then with permission | Rejected; rejected (403); accepted with audit record |
| T22 | Rate change at validity boundary | DE reduced rate row ending 31.12.2026 and a new row from 01.01.2027 (synthetic test data) | Supply date 31.12.2026 uses old rate, 01.01.2027 uses new rate |
| T23 | Zero-decimal currency | 3 x 1,001 JPY at 10 % (generic pack test data) | VAT 300 JPY (300.3 rounded), no fractional minor units |

## Alternatives considered

- **Floats or DECIMAL in PHP strings without a money library:** error-prone, no currency safety. Rejected.
- **moneyphp/money:** mature, but brick/money offers rational intermediate values and explicit contexts, which fit our rounding points. Rejected.
- **Per-line VAT rounding as default:** common in some ERP systems, but conflicts with EN 16931 BR-CO-17 and creates differences between PDF and XML. Rejected as default, kept as an option.
- **External tax engine (Avalara, Vertex):** paid SaaS dependency, not acceptable for Community on AMP; Avalara/TaxJar adapters remain a later US module.
- **Sequence numbers via database sequences or auto-increment:** not gapless under rollback and not portable across MySQL and PostgreSQL. Rejected.
- **Numbering at draft creation:** creates gaps when drafts are discarded. Rejected.

## Consequences

**Positive**
- Totals are identical in UI, PDF, XML, DATEV export and reports.
- The tax engine is testable in isolation with table-driven tests and can be reviewed by an accountant as a decision table.
- Numbering works without Redis on all supported databases.

**Negative and risks**
- Finalization serializes per sequence; acceptable for expected volumes (assumption A-12).
- Legal correctness depends on the accountant confirming the marked items before 01.12.2026.
- The category code for services outside the scope of VAT (O vs. other codes) interacts with EN 16931 rules that forbid VAT identifiers on "O" invoices; this needs a decision before the first such e-invoice (ADR-0008).

## Open questions (accountant / product owner)

1. **[accountant]** PROJEKTRA invoices to German business customers: confirm `BA_EXPORT_SERVICE_B2B`, the exact notice wording in English and Bosnian, whether the German §13b reference must appear, and whether a BAM equivalent must be shown.
2. **[accountant]** For e-invoices from PROJEKTRA to German customers: which EN 16931 VAT category to use (AE with PROJEKTRA's BiH VAT number as seller identifier, or O). Rule BR-AE-02 requires seller and buyer VAT identifiers; BR-O-02 forbids them.
3. **[accountant]** BiH: VAT treatment of services to foreign private persons (row 14), the PDV registration threshold, mandatory BiH invoice fields, and retention period.
4. **[accountant]** Confirm German retention of 8 years for invoices issued from 2025 and the start rule (end of calendar year).
5. **[accountant]** Confirm the category code and notice for German services to non-EU businesses (O vs. other) and for Art. 59 services to non-EU private persons.
6. **Product owner:** default number series for cancellation invoices: same series as invoices (proposal, simpler for gapless checks) or a separate `CN-{YYYY}-{####}` series.
7. **Product owner:** minimum justification length and whether overrides require a second approver for amounts above a threshold.
