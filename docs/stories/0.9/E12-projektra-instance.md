# E12 PROJEKTRA instance (customer zero) and first customer delivery

Epic goal: PROJEKTRA runs its own business on Projektrack from 0.9, with the Bosnia and Herzegovina country pack, and the first customer license is delivered on 1 December 2026 as a managed dedicated instance on PROJEKTRA servers (decision C-03). PROJEKTRA's real business data never leaves the production instance; staging uses synthetic data only.

Estimates include implementation or operations work, tests, review and docs.

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0980 | PROJEKTRA production instance provisioned and protected | Must | 12 | Hardening |
| S-0981 | BA configuration and documents for PROJEKTRA | Must | 10 | L3 |
| S-0982 | Fiscalization decision record | Must | 3 | L3 |
| S-0983 | Customer zero acceptance run | Must | 8 | Hardening |
| S-0984 | First customer managed dedicated instance and license | Must | 8 | Hardening |

Epic total: Must 41 h.

---

### S-0980 PROJEKTRA production instance provisioned and protected

| Field | Value |
|---|---|
| Persona | Instance operator (PROJEKTRA operations) |
| Priority | Must (delivery critical) |
| Estimate | 12 h (provisioning 6, backup and restore drill 3, monitoring and docs 3) |
| Sprint | Hardening (29-30 Nov); host preparation may start in L3 |
| Dependencies | S-0923, S-0927, S-0985 to S-0988 |
| Modules | deploy |
| Edition | Business |
| Reviewers | security, qa |

**Story:** As PROJEKTRA's operator, I want the production instance on an EU VPS with a PROJEKTRA tenant and a demo tenant, TLS, backups and monitoring, so that PROJEKTRA can run its business and sell licenses from it.

```gherkin
Scenario: Instance live
  Then the instance at the placeholder domain app.projektra.example runs the release candidate with tenants "PROJEKTRA IT Consulting" and "Demo"
  And the licensing module and license portal are enabled on it

Scenario: Backups proven
  Then nightly backups are encrypted, stored off-host, and a restore drill on a second host succeeded before go-live

Scenario: Staging protected
  Then the staging instance requires basic auth or IP allowlisting and holds synthetic data only
```

---

### S-0981 BA configuration and documents for PROJEKTRA

| Field | Value |
|---|---|
| Persona | Controller (PROJEKTRA) |
| Priority | Must |
| Estimate | 10 h (configuration and templates 5, tests 3, review 2) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0960, S-0964, S-0951 |
| Modules | invoicing, taxpack-ba |
| Edition | Community |
| Reviewers | finance-tax (with PROJEKTRA's accountant), ux, qa |

**Story:** As PROJEKTRA's controller, I want the BA setup ready out of the box, so that I can invoice domestic and foreign customers correctly from day one.

```gherkin
Scenario: Domestic customer
  Then invoices to domestic customers carry PDV 17 %, are in BAM by default and in Bosnian

Scenario: Foreign business customer
  Then invoices to EU business customers are in EUR, in English by default, without BiH PDV, with the place-of-supply notice confirmed by the accountant (A-04)

Scenario: Numbering and documents
  Then invoices are numbered INV-2026-0001 and following (A-09), with PROJEKTRA's logo (placeholder until A-02), bank details and legal entity data, in Word and PDF
```

---

### S-0982 Fiscalization decision record

| Field | Value |
|---|---|
| Persona | Product owner / PROJEKTRA management |
| Priority | Must |
| Estimate | 3 h (analysis and decision record) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | none |
| Modules | taxpack-ba (documentation) |
| Edition | n/a |
| Reviewers | finance-tax |

**Story:** As PROJEKTRA's management, I want a documented decision on whether our invoice types fall under the fiscalization rules of the Federation of BiH, so that the first real invoice is compliant.

```gherkin
Scenario: Decision before first real invoice
  Then docs/decisions contains the analysis and the open question with the accountant's confirmation
  And until a fiscal adapter exists, invoices use the external fiscal reference of S-0965
  And if the answer requires an adapter, it is escalated immediately as a 0.9 blocker (assumption A-05)
```

---

### S-0983 Customer zero acceptance run

| Field | Value |
|---|---|
| Persona | PROJEKTRA project lead and controller |
| Priority | Must |
| Estimate | 8 h (end-to-end run 5, defect triage 3) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | S-0980, S-0981, all Must L2 and L3 stories |
| Modules | all 0.9 modules |
| Edition | Business |
| Reviewers | qa, finance-tax, ux |

**Story:** As PROJEKTRA's project lead and controller, I want to run our real monthly flow on the production instance before the customer gets it, so that issues are found by us first.

```gherkin
Scenario: End-to-end internal flow
  Then PROJEKTRA creates a client, a project with contract, books a week of time, approves it, generates the November service report, drafts an invoice and issues the first license on the instance
  And every defect found is triaged; any defect in money, tax, permissions or isolation is a release blocker

Scenario: Customer zero rule
  Then the deviation from the "3 working days of internal use" rule for 0.9 is recorded in the go/no-go record with the product owner's approval
```

---

### S-0984 First customer managed dedicated instance and license

| Field | Value |
|---|---|
| Persona | Customer admin of the first customer |
| Priority | Must (delivery critical) |
| Estimate | 8 h (provisioning 4, license issue and verification 2, onboarding session preparation 2) |
| Sprint | Hardening (29-30 Nov), executed on Delivery day 1 Dec |
| Dependencies | S-0923, S-0975, S-0976, S-0980, S-0929 |
| Modules | deploy, licensing |
| Edition | Business |
| Reviewers | security, qa |

**Story:** As the first customer's admin, I want my dedicated instance ready with a valid license and a first admin account on 1 December, so that we can start onboarding the same day.

```gherkin
Scenario: Delivery on 1 December
  Given the go/no-go on 30 Nov was "go"
  Then a dedicated instance runs the signed 0.9.0 release on PROJEKTRA servers under the agreed domain with TLS and backups
  And the Founding Customer license is issued from the PROJEKTRA instance and activated on it
  And the customer admin receives the invitation and activation email
  And the onboarding session is held with the workspace wizard and the "Getting started" checklist
```
