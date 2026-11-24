# Changelog

All notable changes of Projektrack are recorded here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/). Versions follow [Semantic Versioning](https://semver.org/).

Release notes for users: `docs/release-notes/`.

## [0.9.0] - unreleased

Founding Edition, Early Access. Planned for 01.12.2026. By decision C-06, only stories that passed the full Definition of Done are switched on. This list is what is built, not yet what is released.

### Added

- Core: modules per company, separate data per company, roles, project roles and field permissions.
- Sign-in: sessions, two-factor sign-in (TOTP) enforced per role, recovery codes.
- Audit log with a hash chain and a verification command.
- Settings, notifications, brand per company, light and dark mode.
- Invitations with roles, and a dashboard with figures of the modules.
- REST API v1 with personal access tokens and an OpenAPI document.
- Languages `bs`, `en` and `de`, own wording, own languages, regional formats, time zones, layout for right to left.
- Customers with contacts, sites and tax profile.
- Projects with team, contracts, work packages and budget alerts.
- Weekly timesheet, timer, approval, correction entries and month close.
- Sales rates and cost rates with validity, job roles, exchange rates and contribution margin. Values of approved time are frozen.
- Service reports from approved time as PDF, DOCX, XLSX and CSV. A generated report never changes.
- Invoices for sellers in Germany and Bosnia and Herzegovina: drafts from time or free, numbers without gaps, cancellation, partial credit notes, payments, open items, email, reference of an external fiscal system.
- Tax engine with tax cases and a documented choice by a person.
- E-invoices: ZUGFeRD and XRechnung (CII and UBL).
- Signed licenses with offline check, license screen, installation from a file.
- Sale of licenses on the licensor's own instance: plans, orders, invoice draft, file for offline signing. Not part of any customer release.
- What is new, under About.
- Installation on plain hosting from the ZIP; encrypted backup and restore.
- Docker Compose stack; operations commands for install, backup, restore and update with rollback.
- Signed release pipeline with checksums and SBOM.
- Synthetic sample data: `php artisan projektrack:demo <slug>`.
- Quick starts in `bs`, `en` and `de`; installation guide for plain hosting in the same languages.

### Security

- No page can be indexed, no referrer is sent, nothing is revealed about the software before sign-in.
- Strict content security policy, no third-party requests.
- Shared links with limits; system check of the installation from outside.
- Credit notes never exceed the invoice.

### Known issues

- Legal notices on invoices need confirmation by an accountant.
- E-invoices are not checked with KoSIT or veraPDF.
- Runs of the business modules on MySQL, MariaDB and PostgreSQL, with Docker and on Apache are still open.
- Full list: `docs/release-notes/0.9/en.md`.
