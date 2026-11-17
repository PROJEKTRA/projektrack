# E06 Time tracking

Epic goal: employees book on any number of projects and work packages quickly; project leads approve; controllers lock periods. Time is stored as integer minutes, timestamps in UTC. Approved times are never deleted, only corrected through traceable correction entries.

Estimates include implementation, tests (tests first for time logic), review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0944 | Time entry domain, validation, rounding and roll-up | Must | 18 | L2 |
| S-0945 | Weekly timesheet grid | Must | 20 | L2 |
| S-0946 | Timer and day view | Must | 10 | L2 |
| S-0947 | Approval workflow with locking and correction entries | Must | 16 | L2 |
| S-0948 | Monthly period close | Must | 6 | L2 |
| S-0949 | Recurring booking proposals | Should | 10 | L2 |

Epic total: Must 70 h, Should 10 h.

---

### S-0944 Time entry domain, validation, rounding and roll-up

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must |
| Estimate | 18 h (build 8, tests 7, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0942 |
| Modules | timesheets |
| Edition | Community |
| Reviewers | finance-tax, qa, security |

**Story:** As an employee, I want my bookings validated and rounded by clear rules and rolled up automatically, so that my hours are correct for reports and invoices.

```gherkin
Scenario: Storage
  Then durations are stored as integer minutes and dates are evaluated in the tenant time zone

Scenario: Validation
  Then an entry without duration is rejected
  And booking on a closed work package, a work package the user is not assigned to, or a locked period is rejected with an actionable message
  And more than 10 h per day (configurable) shows a warning but is allowed

Scenario: Mandatory description
  Given the tenant rule "description required from 0.5 h"
  When an employee books 45 minutes without description
  Then the entry is rejected with the message naming the rule

Scenario: Rounding per contract
  Given contract "SOW-2026-03" rounds up to 15 minutes with a minimum booking of 15 minutes
  When 22 minutes are booked
  Then 30 minutes are billable and the original 22 minutes remain visible

Scenario: Roll-up
  Then each entry is summed into its work package, contract and project totals
```

---

### S-0945 Weekly timesheet grid

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must |
| Estimate | 20 h (build 12, tests 5, review and docs 3) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0944, S-0950 (rate display) |
| Modules | timesheets |
| Edition | Community |
| Reviewers | ux, security, qa |

**Story:** As an employee, I want a spreadsheet-like weekly timesheet across all my projects, so that I book my week in a couple of minutes using only the keyboard.

```gherkin
Scenario: Employee books on several projects in one week
  Given an employee assigned to three work packages in two projects
  When he enters times for every day on all three work packages in the weekly timesheet
  Then the times are summed correctly per work package, contract and project
  And an employee without permission "view_rates" sees no rates or amounts

Scenario: Keyboard first
  Then arrow keys, Tab and Enter move between cells, typing "1,5" or "1:30" books 90 minutes
  And adding a row for a work package takes at most 3 interactions

Scenario: Arabic user works in RTL
  Given a user with language "ar"
  When he opens the weekly timesheet
  Then the layout is mirrored, days run right to left
  And ticket keys and amounts are displayed correctly isolated

Scenario: Optimistic save with undo
  Then every cell saves instantly with an undo option and shows validation errors inline
```

---

### S-0946 Timer and day view

| Field | Value |
|---|---|
| Persona | Employee |
| Priority | Must |
| Estimate | 10 h (build 6, tests 3, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0944 |
| Modules | timesheets |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As an employee, I want to start and stop a timer on a work package and see my day, so that I capture time while I work.

```gherkin
Scenario: Timer
  When the employee starts a timer on "GG Operations > 2 Consulting" at 09:00 and stops it at 10:10
  Then a draft entry of 70 minutes is created and rounding rules are applied on save

Scenario: Only one running timer
  When a second timer is started
  Then the first one stops and its entry is saved

Scenario: Timer survives reload
  Then a running timer continues after page reload and across devices of the same user
```

---

### S-0947 Approval workflow with locking and correction entries

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Must |
| Estimate | 16 h (build 8, tests 6, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0944, S-0906, S-0912 |
| Modules | timesheets |
| Edition | Community |
| Reviewers | finance-tax, security, ux, qa |

**Story:** As a project lead, I want employees to submit weeks and me to approve or reject them, so that only checked times reach reports and invoices.

```gherkin
Scenario: Submit and approve
  Given an employee submits week 45/2026
  When the project lead approves the entries of his project
  Then those entries are locked and marked approved with approver and timestamp

Scenario: Reject with reason
  When the project lead rejects an entry with reason "wrong work package"
  Then the employee is notified and can change and resubmit that entry

Scenario: Correction instead of change
  Given an approved entry of 4 h
  When a correction to 3 h is needed
  Then a correction entry of minus 1 h with reason and author is created
  And the original entry is unchanged and both are visible in the history

Scenario: Approver cannot approve own times
  Then a project lead's own entries require approval by another approver
```

---

### S-0948 Monthly period close

| Field | Value |
|---|---|
| Persona | Controller |
| Priority | Must |
| Estimate | 6 h (build 3, tests 2, review and docs 1) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0947 |
| Modules | timesheets |
| Edition | Community |
| Reviewers | finance-tax, qa |

**Story:** As a controller, I want to lock a month, so that nobody books or changes times in a period that has been reported or invoiced.

```gherkin
Scenario: Lock October
  When the controller locks October 2026 for the tenant
  Then new entries, changes and corrections dated in October are rejected
  And unapproved October entries are listed before locking
  And reopening requires permission, a reason and is audited
```

---

### S-0949 Recurring booking proposals

| Field | Value |
|---|---|
| Persona | Project lead |
| Priority | Should |
| Estimate | 10 h (build 5, tests 3, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0944 |
| Modules | timesheets |
| Edition | Community |
| Reviewers | ux, qa |

**Story:** As a project lead, I want recurring bookings such as "Daily stand-up 0.25 h on working days" proposed to participants, so that routine time is captured without blind automation.

```gherkin
Scenario: Proposal, not automatic booking
  Given a recurring rule "Daily stand-up 15 min on working days" for project "GG Operations"
  Then each participant sees proposed entries in the timesheet that become bookings only when confirmed
```
