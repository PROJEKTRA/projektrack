# E07 Hourly rates and prices

Epic goal: a fully configurable rate hierarchy with validity periods that never changes approved or invoiced values retroactively, separate sales and cost rates, and currencies with stored conversion rates. Money is stored as integers in minor units (brick/money); no floats.

Estimates include implementation, tests (tests first), review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0950 | Rate hierarchy and price lists with validity periods | Must | 22 | L2 |
| S-0951 | Currencies and stored exchange rates | Must | 8 | L2 |
| S-0952 | Surcharges (weekend, public holiday, night, on-call) | Should | 8 | L2 |

Epic total: Must 30 h, Should 8 h.

---

### S-0950 Rate hierarchy and price lists with validity periods

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 22 h (build 10, tests 9, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0939 to S-0942, S-0944, S-0906 |
| Modules | rates |
| Edition | Community |
| Reviewers | finance-tax, security, qa |

**Story:** As a controller, I want rates defined at eight levels with validity periods and the applied source shown on every entry, so that every hour is valued correctly and traceably.

```gherkin
Scenario: Most specific rule wins
  Given a tenant default of 70 EUR/h, a role rate "Senior IT Project Lead" of 100 EUR/h and a contract rate of 95 EUR/h for that role
  When a Senior IT Project Lead books on a work package of that contract
  Then the entry is valued at 95 EUR/h and shows the source "Contract SOW-2026-03"

Scenario: Hierarchy order
  Then the order is individual entry, work package, contract, project, customer, employee, role, tenant default
  And an individual entry rate requires the permission "rates.override_entry" and is audited

Scenario: Rate change is not retroactive
  Given the rate for Senior IT Project Lead is 100 EUR/h until 31.10.2026
  And 110 EUR/h from 01.11.2026
  When a service report for October and November is generated
  Then October entries are valued at 100 EUR/h and November entries at 110 EUR/h

Scenario: Approved values are frozen
  Given an approved entry valued at 100 EUR/h
  When the price list is edited for that date
  Then the approved entry keeps 100 EUR/h

Scenario: Sales and cost rate
  Then each level holds a sales rate and a cost rate, and contribution margin is computed per entry
  And cost rates and margins are visible only with "rates.view_cost"

Scenario: Demo rates
  Then the demo seed contains Junior Consultant 80 EUR/h, Senior IT Project Lead 100 EUR/h and Technical Architect 130 EUR/h
```

---

### S-0951 Currencies and stored exchange rates

| Field | Value |
|---|---|
| Persona | Controller (PROJEKTRA) |
| Priority | Must |
| Estimate | 8 h (build 3, tests 4, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0950 |
| Modules | rates, core |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As PROJEKTRA's controller, I want rates and invoices per customer or contract currency with a stored exchange rate, so that EUR and BAM business is valued correctly.

```gherkin
Scenario: Fixed BAM rate
  Then the exchange rate EUR to BAM is 1.95583 by default (currency board)
  And 100.00 EUR converts to 195.58 BAM with documented rounding (half up on minor units)

Scenario: Manual rates for other currencies
  When the controller enters USD/EUR 0.9200 valid from 01.11.2026
  Then conversions on or after that date use it and store the rate used on the entry or invoice

Scenario: No floats
  Then all amounts are integers in minor units and a test asserts no float arithmetic in the money layer
```

---

### S-0952 Surcharges (weekend, public holiday, night, on-call)

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Should |
| Estimate | 8 h (build 3, tests 4, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0950 |
| Modules | rates |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want surcharges as percentage or fixed amount, so that weekend and on-call work is billed as agreed.

```gherkin
Scenario: Weekend surcharge
  Given a weekend surcharge of 50 % on contract "SOW-2026-03"
  When 2 h are booked on Saturday 07.11.2026 at 100 EUR/h
  Then the entry is valued 300.00 EUR with the surcharge shown separately
```
