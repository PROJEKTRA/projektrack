# E14 Documentation, tutorials and usability

Epic goal: documentation is part of the product. 0.9 ships the installation guide, an admin quick start and user quick starts per role in `bs`, `en` and `de`, plus the in-app "Getting started" tutorials for booking time and creating a service report. Release notes and a usability test close the milestone.

Estimates include writing, screenshots generated in CI, translation review and technical review.

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0989 | Installation guide (AMP and Docker) in bs, en, de | Must | 8 | Hardening |
| S-0990 | Admin quick start in bs, en, de | Must | 8 | Hardening |
| S-0991 | User quick starts for employee, project lead and controller | Must | 12 | Hardening |
| S-0992 | Tour engine, help panel and "Getting started" tutorials | Must | 16 | L3 |
| S-0993 | Release notes, changelog, "What's new" and demo script | Must | 6 | Hardening |
| S-0994 | Moderated usability test of core tasks | Must | 8 | Hardening |

Epic total: Must 58 h.

---

### S-0989 Installation guide (AMP and Docker) in bs, en, de

| Field | Value |
|---|---|
| Persona | Self-hosting admin; instance operator |
| Priority | Must |
| Estimate | 8 h (writing 4, verification on fresh server 2, translation review 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | S-0923, S-0925, S-0926 |
| Modules | docs |
| Edition | Community |
| Reviewers | qa, security |

**Story:** As a self-hosting admin, I want a step-by-step installation guide, so that I can install without help.

```gherkin
Scenario: Guide is verified
  Then docs/admin/installation-amp.md and installation-docker.md exist in bs, en and de
  And a person following only the guide installs the release candidate on a fresh server successfully
  And the guide explains custom domains and Certificate Transparency visibility
```

---

### S-0990 Admin quick start in bs, en, de

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 8 h (writing 5, translation review 2, technical review 1) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | E04, E09, E11 |
| Modules | docs |
| Edition | Community and Business |
| Reviewers | ux, finance-tax |

**Story:** As a tenant admin, I want a quick start covering workspace setup, users and roles, tax pack, e-invoicing and license activation, so that I configure the essentials in one sitting.

```gherkin
Scenario: Quick start content
  Then the quick start covers workspace wizard, invitations and role templates, rates, tax pack and numbering, e-invoice defaults, license activation and updates
  And screenshots are generated in CI per language
```

---

### S-0991 User quick starts for employee, project lead and controller

| Field | Value |
|---|---|
| Persona | Employee, project lead, controller |
| Priority | Must |
| Estimate | 12 h (writing 7, translation review 3, technical review 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | E05 to E10 |
| Modules | docs |
| Edition | Community |
| Reviewers | ux, finance-tax (controller guide) |

**Story:** As an employee, project lead or controller, I want a short guide for my role in my language, so that I can do my daily tasks without training.

```gherkin
Scenario: Role guides
  Then the employee guide covers booking a week, the timer and submitting for approval
  And the project lead guide covers projects, work packages, approvals and service reports
  And the controller guide covers rates, period close, invoices, e-invoices, cancellation and payments
  And each exists in bs, en and de
```

---

### S-0992 Tour engine, help panel and "Getting started" tutorials

| Field | Value |
|---|---|
| Persona | Employee (new user) |
| Priority | Must (simplification candidate) |
| Estimate | 16 h (build 9, tests 4, review and docs 3) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0945, S-0953, S-0936 |
| Modules | help, onboarding |
| Edition | Community |
| Reviewers | ux, security, qa |

**Story:** As a new user, I want a guided in-app tutorial for booking time and creating a service report, so that I succeed the first time without reading documentation.

```gherkin
Scenario: Self-hosted tour engine
  Then tutorials run without any third-party script or tracking and work offline on self-hosted installations

Scenario: Tutorials
  Then "Book your first week" and "Create a service report" can be started from the help panel and the "Getting started" checklist
  And they work in bs, en, de and in RTL (ar)

Scenario: Contextual help
  Then a "?" on the timesheet, service report and invoice screens opens the matching help article in a side panel
```

---

### S-0993 Release notes, changelog, "What's new" and demo script

| Field | Value |
|---|---|
| Persona | Tenant admin |
| Priority | Must |
| Estimate | 6 h (writing 4, translation review 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | All stories |
| Modules | docs, core |
| Edition | Community |
| Reviewers | ux |

**Story:** As a tenant admin, I want release notes and a "What's new" panel, so that I know what 0.9 contains and what is not included yet.

```gherkin
Scenario: Release information
  Then CHANGELOG, release notes in bs, en and de, and the "What's new" panel describe 0.9 including known limitations and scope moved to 0.10
  And docs/demos/0.9.md contains a demo script based on synthetic demo data
```

---

### S-0994 Moderated usability test of core tasks

| Field | Value |
|---|---|
| Persona | Product owner |
| Priority | Must |
| Estimate | 8 h (preparation 2, 5 sessions 4, report 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | Release candidate |
| Modules | n/a |
| Edition | n/a |
| Reviewers | ux |

**Story:** As the product owner, I want a moderated usability test with 5 participants on the core tasks, so that we know whether booking time and creating a service report work without training.

```gherkin
Scenario: Usability results
  Then 5 participants perform "book a week", "create a service report" and "create an invoice from a service report"
  And the results with task success rate (target at least 90 %) are documented in docs/ux/usability/0.9.md
  And every failed task is turned into a story or a fix before or right after release
```
