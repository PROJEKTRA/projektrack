# Release notes 0.9.0 "Founding Edition" (Early Access)

State: 24.11.2026, not released. Planned for 01.12.2026.

0.9 is delivered in stages (decision C-06). The Early Access build switches on only what passed the full Definition of Done by the go/no-go on 30.11.2026. The list below is what is built. The business modules have not passed all checks yet.

## What is in

| Area | Content |
|---|---|
| Access | Sign-in, two-factor sign-in per role, roles and project roles, audit log, API tokens |
| Languages | `bs`, `en`, `de`, own wording, own languages, regional formats |
| Company | Brand, settings, notifications, invitations, dashboard, light and dark mode |
| Customers and projects | Contacts, tax profile, team, contracts, work packages, budget alerts |
| Time | Weekly timesheet, timer, approval, corrections, month close |
| Rates | Sales and cost rates, job roles, exchange rates, contribution margin |
| Service reports | From approved time, as PDF, DOCX, XLSX and CSV |
| Invoices | Sellers in DE and BA, numbers without gaps, cancellation, partial credit notes, payments, email, PDF |
| E-invoices | ZUGFeRD and XRechnung (CII, UBL) |
| License | Signed license, checked offline; without a license the installation runs as Community |
| Operation | Plain hosting from the ZIP, Docker Compose, encrypted backup and restore, system check |

## Known limitations

### Before you issue real invoices

- **Legal notices need an accountant.** The wording is a proposal. Invoices with a notice are blocked until you confirm in the settings that an accountant checked it.
- Open questions for the accountant: code for exports from BiH, small business with EU customers, retention, place of issue and fiscal number in BiH, KM equivalent on tax-free EUR invoices.
- Fiscalization in the Federation of BiH is not confirmed. An external fiscal reference can be recorded.
- **E-invoices are not checked with KoSIT or veraPDF.** Check each one with the official validators before you send it.
- E-invoices for credit notes do not use the document type 381 yet. A wrong credit note cannot be taken back.

### Not tested yet

- **Runs of the business modules on MySQL, MariaDB and PostgreSQL are still open.** So are their runs with Docker and on Apache. Use test data until they are done.
- Restore of a plain hosting backup on MySQL and MariaDB is not run automatically. PostgreSQL is not supported for it.
- No usability test took place yet.

### Functions

- Plain hosting: no web installer and no automatic update.
- The end of a forgotten timer cannot be corrected.
- Users cannot be deactivated.
- The number format and first number of invoices cannot be changed.
- The issued PDF is not archived yet. There is no nightly check for gaps in the numbers.
- An invoice is made from time, not from a service report.
- Exchange rates may be at most 31 days old. This limit is not confirmed.
- Service reports have built-in templates only.

### Keys

- The release key and the license key are not in the build yet. Until then releases cannot be verified, and licenses cannot be installed.

## Moved to later releases

The cuts table names these targets. Where it names none, the release is not fixed.

| Moved | Target |
|---|---|
| Web installer, one-command Docker script | 0.9.x |
| One-click update in the browser | 0.10 |
| Upload and assignment of report templates | 0.10 |
| Invoices as Word document | 0.10 |
| Workspace wizard, welcome flow, tours, permission matrix, more project templates | not fixed |
| License portal, online activation, daily check-in | not fixed |
| Passkeys | not fixed |
| Right to left PDF, more preview languages | not fixed |
| Generic EU country pack | not fixed |
| Role quotas in contracts | not fixed |

Two cuts were built ahead of plan: editing texts per company and XRechnung in UBL.

Planned for the replanned 0.10: command palette and search, "view as", customer import, activity timeline, recurring bookings, surcharges, batch reports, down payment and final invoices, renewal reminders, quotes, absences and expenses.

Not in 0.9: service desk, automation, Gantt, roadmaps, wiki, optional AI features, self-signup, incoming e-invoices.

## Planned stages

| Release | Content | Target |
|---|---|---|
| 0.9.0 | Early Access, what passed all checks | 01.12.2026 |
| 0.9.1 | Foundation complete | about 04.12. to 11.12.2026 |
| 0.9.2 | Customers, projects, time, rates, service reports | about 22.12.2026 to mid January 2027 |
| 0.9.3 | Invoicing, tax, licensing | January to February 2027 |
| 0.9.4 | E-invoices, onboarding, usability test | end of January to late February 2027 |

Dates are targets. The dates of 0.10 and 1.0 are being replanned. Open decisions: e-invoicing as a later phase (P1) and the manual sale flow (P2).

## Install

`docs/admin/plain-hosting.md` or `docs/admin/docker.md`. First steps: `docs/user/en/`.
