# Projektrack: Product Requirements Document

| Item | Value |
|---|---|
| Product | Projektrack ("Projektrack by PROJEKTRA") |
| Owner and licensor | PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting) |
| Pilot customer | Learnteq UG (haftungsbeschränkt), Stuttgart |
| Status | Phase 0, as of 16.11.2026 |
| First release | 0.9 "Founding Edition" on 1 December 2026 (fixed date) |
| Related documents | `personas.md`, `glossary.md`, `roadmap.md`, `../decisions/assumptions.md`, `../backlog.md` |

This document is the product truth for Projektrack. Requirement IDs are stable. New requirements get new IDs; removed requirements are marked as removed, never renumbered.

---

## 1. Vision

Projektrack is one modular, secure, white-label platform for project management, time tracking, service reports, controlling, quotes, invoicing including e-invoicing, ticketing and service management, workflows and automation, product roadmaps, Gantt planning, absence planning and knowledge management.

Tagline (default, configurable, translatable): **"Plan it. Track it. Prove it."** Planning, delivery and proof of performance (service reports, invoices) live in one product.

It combines what teams today buy from several tools:

- Projektron-level depth: work breakdown structure, plan vs. actual, controlling, service reports, quotes and invoices.
- Issue tracking, boards, sprints, query language, configurable workflows and automation.
- A service desk with customer portal, SLAs, queues and ITSM processes.
- A wiki with spaces, page tree, versioning and templates.
- The simplicity of lightweight time tracking and invoicing tools.
- E-invoicing (create, validate, send, receive, archive) in a few clicks.
- Interactive Gantt planning with dependencies and baselines.
- Modular apps with per-user licensing and a free Community edition.

### 1.1 The three measures

Every decision is measured against three words.

| Measure | Meaning | How we check it |
|---|---|---|
| **Advanced** | Depth where professionals need it: rate hierarchies, tax engine, e-invoicing, workflows, earned value, critical path. | Feature parity checks against the benchmark depth products per module. |
| **Secure** | Secure by design and by default. Never traded away. | OWASP ASVS Level 2 baseline, Level 3 for auth, permissions and finance; independent security review per milestone; external penetration test before 1.0. |
| **Easy** | A new user is productive without training. | Time to first value under 10 minutes; task success at least 90 %; SUS at least 85 before 1.0. |

When the measures conflict: "easy for the user" wins over "easy for the developer". "Secure" is never traded away.

---

## 2. Target customers

| Segment | Description | Typical size | First edition |
|---|---|---|---|
| IT service and consulting companies | Bill time and material or fixed price, need monthly service reports and correct invoices. Core segment and PROJEKTRA's own business. | 5 to 200 users | Business |
| Agencies and project-based service firms | Web, LMS, e-learning, marketing agencies with retainers and hour packages. Learnteq is the pilot. | 5 to 100 users | Business |
| Managed service providers and internal IT | Service desk, SLAs, ITSM processes, maintenance contracts. | 10 to 500 users | Business, Enterprise |
| Product teams | Backlog, sprints, roadmaps, releases, prioritization. | 5 to 200 users | Community, Business |
| Public-sector suppliers in Germany | Need XRechnung with Leitweg-ID and GoBD-compliant archiving. | Any | Business |
| US service companies | Need en-US formats, sales tax, US invoicing conventions (from 1.0). | 5 to 500 users | Business, Enterprise |
| Partners and resellers | Run white-labeled instances or tenants for their own customers. | n/a | Partner |
| Self-hosters | Want a sovereign tool on plain AMP hosting, Docker or Kubernetes. | Any | Community |

Primary markets: Bosnia and Herzegovina, Germany (DACH), the wider EU, then the United States.

---

## 3. Personas (summary)

Full persona blocks are in `personas.md`.

| Persona | Core job | Top need |
|---|---|---|
| Employee | Books time on several projects, requests leave, works on tasks. | Book a week in under 2 minutes, no training. |
| Project lead | Plans and steers projects, approves time, sends service reports. | One-click service report and a clear budget view. |
| Controller / finance | Locks periods, issues invoices, handles tax and e-invoicing. | Correct tax case, gapless numbering, valid e-invoices. |
| Service agent | Works tickets in queues under SLAs. | See what is urgent, reply fast, never breach an SLA unnoticed. |
| Product owner | Manages backlog, roadmap, releases. | Prioritize with data and share a roadmap safely. |
| Admin | Installs, configures, secures and updates the system. | Safe one-click updates, clear permissions, license in one step. |
| Client portal user | Customer contact who follows requests, signs off reports, views invoices. | See only their own data, act from a link without training. |
| Freelancer | External contributor who books time and expenses. | A small, focused interface with only their projects. |

---

## 4. Problem statements

| ID | Problem | Who feels it | Projektrack answer |
|---|---|---|---|
| PS-01 | Monthly service reports are assembled by hand in spreadsheets. Totals break after edits; formats differ per customer. | Project lead, controller | One-click reports from approved time with presets, templates and XLSX with real formulas. |
| PS-02 | Time is tracked in one tool, invoiced in another, controlled in a third. Data is copied and drifts. | Controller, project lead | Time, rates, reports, invoices and controlling share one data model. |
| PS-03 | Tax cases (reverse charge, exemptions, BiH PDV) are applied manually and wrong. | Controller | A tested tax engine picks the tax case, shows the reason and logs overrides. |
| PS-04 | E-invoicing (ZUGFeRD, XRechnung) needs XML knowledge or extra tools. | Controller | E-invoices from the normal invoice form, validated before sending. |
| PS-05 | Rate changes silently change past values. | Controller | Price lists with validity periods; approved and invoiced times never change. |
| PS-06 | Powerful tools are hard to learn; simple tools lack depth. | All users | Simple by default, powerful on demand. |
| PS-07 | Project tools leak instance URLs to search engines and third parties. | Admin, security officer | Invisible by default, enforced in code and tested in CI. |
| PS-08 | Self-hosting usually needs Docker or a DevOps team. | Admin, small companies | Runs fully on a plain AMP stack; Docker and Kubernetes optional. |
| PS-09 | Non-English and RTL teams get poor translations and broken layouts. | Users in bs, ar, fa and others | Moodle-style string system, admin overrides, full RTL from day one. |
| PS-10 | Applying or changing a license needs a support ticket. | Admin | Activation code or license file, applied in one step. |
| PS-11 | AI features send data to providers without clear control. | Admin, data protection officer | AI is optional, off by default, with per-module data control and human approval. |

---

## 5. Module requirements

Priority key: **M** = must for the release named, **S** = should, **L** = later. "Rel." is the target release (see `roadmap.md`). Edition key: **C** = Community, **B** = Business, **E** = Enterprise.

### 5.1 Core (always active)

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-CORE-01 | Multi-tenancy: several companies in one instance with strictly separated data. Per tenant: branding, numbering, tax and bank details, default language, currency, time zone. | 0.9 | C |
| PRD-CORE-02 | Global roles: Admin, Controller, Project Lead, Employee, External/Freelancer, Customer. Role templates also include Service Agent and Product Owner. Project roles per project. | 0.9 | C |
| PRD-CORE-03 | Fine-grained permissions including field-level permissions for rates, costs, margins and salaries. Deny by default. | 0.9 | C |
| PRD-CORE-04 | User types for licensing: Full user, Light user (time, expenses, own tasks, approvals), Portal user (customers, free). | 0.9 | C |
| PRD-CORE-05 | Module registry: modules enabled or disabled per tenant at runtime. Dependencies checked and explained in plain language. Disabling a module keeps its data. | 0.9 | C |
| PRD-CORE-06 | Settings UI for everything configurable. No configuration that is only possible via files. | 0.9 | C |
| PRD-CORE-07 | Notifications in-app and by email, optional webhook; configurable per user; digest mode. | 0.9 | C |
| PRD-CORE-08 | Global search and command palette (Ctrl/Cmd+K). | 0.9 | C |
| PRD-CORE-09 | Immutable, tamper-evident audit log (hash chain) for logins, permission changes, financial records, exports and settings. | 0.9 | C |
| PRD-CORE-10 | Feature flags in addition to modules for gradual rollouts. | 0.9 | C |
| PRD-CORE-11 | Extension points for third-party modules: menus, dashboard widgets, fields, export templates, automation actions, tax packs, payment adapters. Modules declare capabilities; admins approve them on install; PROJEKTRA-signed modules are marked verified. | 1.0 (hooks), 2.0 (public SDK) | C |
| PRD-CORE-12 | Every UI function is also available through the REST API. | 0.9 | C |

### 5.2 Customers and contacts (CRM light)

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-CRM-01 | Companies, sites, contacts, billing addresses, payment terms, document language, currency. | 0.9 | C |
| PRD-CRM-02 | VAT ID with optional EU VIES validation. Tax status: domestic, EU business, non-EU, private, exempt. | 0.9 | C |
| PRD-CRM-03 | Routing ID (Leitweg-ID) for public-sector e-invoices; preferred e-invoice format and delivery channel per customer. | 0.9 | C |
| PRD-CRM-04 | Customer-specific price lists and hourly rates. | 0.9 | C |
| PRD-CRM-05 | Activity timeline per customer: quotes, projects, tickets, invoices, documents. | 0.9 (partial), 1.1 (complete) | C |

### 5.3 Projects, contracts and work packages

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-PROJ-01 | Project with customer, project lead, status, period, type, billing model, budget in hours and money, health indicator (traffic light). | 0.9 | C |
| PRD-PROJ-02 | Contract / statement of work as its own entity, several per project. Types: framework agreement with call-offs, time and material, fixed price, hour package, monthly retainer, maintenance/support. | 0.9 | C |
| PRD-PROJ-03 | Contract budget in hours and/or money, term, valid rates, role quotas (for example 40 h Senior Project Lead per month). | 0.9 | C |
| PRD-PROJ-04 | Budget alerts, configurable; default thresholds 70 %, 90 %, 100 %. | 0.9 | C |
| PRD-PROJ-05 | Work breakdown structure: nested work packages of any depth, drag and drop, numbering (1, 1.1, 1.1.2). | 0.9 | C |
| PRD-PROJ-06 | Work package: owner, assigned people, planned hours, planned cost, start/end, percent complete, status, billable flag, linked contract, configurable service category. | 0.9 | C |
| PRD-PROJ-07 | Milestones and dependencies; Gantt view (see 5.15). | 1.0 | C |
| PRD-PROJ-08 | Project templates: new project from template with WBS, roles, workflows, automation rules, wiki space and board. | 0.9 (WBS and roles), 1.0 (full) | C |

### 5.4 Time tracking

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-TIME-01 | An employee books on any number of projects, work packages and tickets in parallel. | 0.9 | C |
| PRD-TIME-02 | Weekly timesheet: rows are work packages or tickets, columns are days, spreadsheet-like keyboard navigation. | 0.9 | C |
| PRD-TIME-03 | Day view, calendar view, timer (start/stop, also from a ticket), quick entry from the command palette. | 0.9 (timer, day view), 1.0 (calendar) | C |
| PRD-TIME-04 | Mobile PWA with offline capture and later sync. | 1.0 | C |
| PRD-TIME-05 | Time booked on a ticket rolls up to work package, contract and project. | 1.0 | C |
| PRD-TIME-06 | Configurable mandatory fields (for example description required from 0.5 h). | 0.9 | C |
| PRD-TIME-07 | Recurring bookings proposed for confirmation (for example daily stand-up 0.25 h on working days), never booked blindly. | 0.10 | C |
| PRD-TIME-08 | Rounding rules per tenant, customer and contract (for example round up to 15 minutes); minimum booking. | 0.9 | C |
| PRD-TIME-09 | Validation: no entries without duration; warning above a configurable daily limit; booking only on open, assigned work packages; no booking in locked periods. | 0.9 | C |
| PRD-TIME-10 | Approval workflow: employee submits week or month, project lead approves, optional customer sign-off. Approved time is locked; corrections only through traceable correction entries. | 0.9 | C |
| PRD-TIME-11 | Period close per month: the controller locks the month. | 0.9 | C |
| PRD-TIME-12 | Working time models, target vs. actual hours, overtime balance (optional HR-light module). Absences come from the absence calendar. | 0.10 | C |
| PRD-TIME-13 | Travel time and expenses (mileage, hotel, per diems), re-billable. | 0.9 (S), else 0.10 | C |
| PRD-TIME-14 | Time is stored as integer minutes; timestamps in UTC; display in tenant or user time zone. | 0.9 | C |

### 5.5 Hourly rates and prices

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-RATE-01 | Rate hierarchy, most specific wins: 1 individual entry (permission required), 2 work package, 3 contract, 4 project, 5 customer, 6 employee, 7 role, 8 tenant default. | 0.9 | C |
| PRD-RATE-02 | The source of the applied rate is shown on every entry. | 0.9 | C |
| PRD-RATE-03 | Price lists with validity periods. Rate changes never affect approved or invoiced time retroactively. | 0.9 | C |
| PRD-RATE-04 | Separate sales rate (external) and cost rate (internal) for contribution margin. | 0.9 | C |
| PRD-RATE-05 | Surcharges (weekend, public holiday, night, on-call) as percentage or fixed amount. | 0.10 | C |
| PRD-RATE-06 | Currencies per customer or contract (EUR, BAM, USD and others). Conversion with the rate stored at booking or invoice date. BAM/EUR uses the fixed currency board rate 1 EUR = 1.95583 BAM; other rates are entered manually in 0.9. | 0.9 | C |
| PRD-RATE-07 | Money is stored as integers in minor units. Never floating point. | 0.9 | C |
| PRD-RATE-08 | Demo data rates: Junior Consultant 80 EUR/h, Senior IT Project Lead 100 EUR/h, Technical Architect 130 EUR/h. | 0.9 | C |

### 5.6 Service reports

The most important feature for internal use. It replaces monthly spreadsheet-based service reports.

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-REP-01 | One-click generation via presets: per work package, per employee (all projects or per project), per project with all employees, per contract, per customer. | 0.9 | C |
| PRD-REP-02 | Period: week, month, quarter, custom. Default: previous month. | 0.9 | C |
| PRD-REP-03 | Content: header (customer, project, contract, period, number); entry table (date in locale format, employee, role, work package or service category, activity, hours, optional rate and amount); totals per role, service category, work package, employee and grand total; contract budget used and remaining; signature fields. | 0.9 | C |
| PRD-REP-04 | Per preset: columns, grouping, sorting (default by date), with or without amounts (customer vs. internal view), document language. | 0.9 | C |
| PRD-REP-05 | Exports: DOCX from editable template, PDF, XLSX with formulas (totals stay correct after manual edits), CSV. All formats work in LTR and RTL. | 0.9 | C |
| PRD-REP-06 | Template management: upload DOCX templates with placeholders and table blocks; assign per tenant, customer, language; preview. | 0.9 | C |
| PRD-REP-07 | Batch: all reports of a month for all projects at once, as ZIP or by email. | 0.10 | C |
| PRD-REP-08 | Scheduled generation at month end, sent to the project lead for review. | 1.0 | B |
| PRD-REP-09 | Snapshot and versioning: an approved report is frozen (immutable, hashed); changes create a new version. | 0.9 | C |
| PRD-REP-10 | Customer sign-off via secure link: view, approve or comment; timestamp, name and IP logged. | 0.10 | B |
| PRD-REP-11 | Create an invoice directly from an approved service report. | 0.9 | C |
| PRD-REP-12 | Import existing spreadsheet service reports with a column mapping wizard. | 1.3 | C |

### 5.7 Quotes

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-QUO-01 | Line items (hours by role, fixed items, materials, licenses), optional and alternative items, line and total discounts, text blocks, attachments. | 0.9 (S), else 0.10 | C |
| PRD-QUO-02 | Versioning (for example Q-2026-014 v1, v2); status draft, sent, accepted, declined, expired; validity date. | 0.9 (S), else 0.10 | C |
| PRD-QUO-03 | Costing view: cost vs. sales, margin and contribution per line. | 0.9 (S), else 0.10 | C |
| PRD-QUO-04 | Every quote exports as an Excel costing sheet (XLSX with formulas); optional re-import of adjusted prices. | 0.9 (S), else 0.10 | C |
| PRD-QUO-05 | DOCX/PDF export via templates, email sending, online acceptance via link. | 0.10 | C |
| PRD-QUO-06 | Accepting a quote creates in one click: project, contract with budget, work packages from line items, rates, optionally wiki space and board. | 0.10 | C |

### 5.8 Invoicing, tax and e-invoicing

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-INV-01 | Invoice types: time and material (from time or service reports), fixed price, down payment, final (deducting down payments), recurring (retainer, maintenance, SaaS), credit note, cancellation invoice. Invoices are never deleted. | 0.9 (T&M, fixed, cancellation, credit note), 0.10 (rest) | C |
| PRD-INV-02 | Gapless numbering sequences per tenant and document type, configurable format. Default `{PREFIX}-{YYYY}-{####}`, reset yearly (for example `INV-2026-0001`). | 0.9 | C |
| PRD-INV-03 | A finalized invoice is immutable. Changes only via cancellation and re-issue. Retention periods per country. | 0.9 | C |
| PRD-INV-04 | Tax engine as a separate, fully tested domain layer, driven by seller country, customer tax status, place of supply and service type. | 0.9 | C |
| PRD-INV-05 | With VAT: standard and reduced rates per country (Germany 19 % / 7 %, Bosnia and Herzegovina PDV 17 %), multiple rates per invoice. | 0.9 | C |
| PRD-INV-06 | Without VAT: reverse charge for EU B2B services (EU VAT Directive Art. 196, German §13b UStG) with legal notice in the document language and both VAT IDs; supplies to non-EU customers (outside the scope, with notice); small business exemption (German §19 UStG); tax-exempt services. | 0.9 | C |
| PRD-INV-07 | The applied tax case is chosen automatically and shown with its reason. Override only with permission and a logged justification. | 0.9 | C |
| PRD-INV-08 | Country packs define rates, mandatory fields, legal notices, numbering rules and retention. Germany, Bosnia and Herzegovina and generic EU first; US from 1.0. | 0.9 | C |
| PRD-INV-09 | Fiscalization adapter interface for countries that require it. Until an adapter exists, an invoice can be marked "issued via external fiscal system" with its fiscal reference. BiH fiscalization adapter in 2.0. | 0.9 (marker), 2.0 (BA adapter) | C |
| PRD-INV-10 | E-invoice creation: every invoice can be issued as ZUGFeRD/Factur-X (PDF/A-3 with embedded XML, profile EN 16931 by default) or XRechnung (UBL and CII) from the normal invoice form. No XML knowledge needed. | 0.9 | C |
| PRD-INV-11 | All mandatory e-invoice fields filled automatically: seller and buyer data, VAT IDs, tax category codes per line, payment terms, bank details, Leitweg-ID, order and contract references. Missing fields are shown inline before finalizing. | 0.9 | C |
| PRD-INV-12 | Validation before sending against EN 16931 and XRechnung rules; readable error list in the user's language. Works on plain AMP (schema and Schematron check). | 0.9 | C |
| PRD-INV-13 | Send by email from Projektrack with per-language templates; download PDF/XML; adapter interface for transmission channels (public portals, Peppol access point). | 0.9 (email), 1.3/2.0 (Peppol) | C |
| PRD-INV-14 | Receive e-invoices by upload or a dedicated inbound mailbox per tenant. XRechnung and ZUGFeRD parsed into a readable view. Proposal of supplier, project, work package and cost category; link to project controlling. | 0.10 | B |
| PRD-INV-15 | Archive: original XML/PDF stored unchanged with hash, audit trail and retention per country pack (GoBD for Germany). | 0.9 | C |
| PRD-INV-16 | Payments, partial payments, open items, due dates. | 0.10 | C |
| PRD-INV-17 | Dunning with levels and templates. | 0.10 | B |
| PRD-INV-18 | Invoices in any supported language including RTL; tenant logo, bank details, optional EPC QR code. | 0.9 | C |
| PRD-INV-19 | Exports: CSV from 0.9; DATEV booking batch CSV; accounting connector contract as an extension point. | 0.9 (CSV), 1.3 (DATEV) | B (DATEV) |
| PRD-INV-20 | PROJEKTRA invoices to EU business customers: EUR, English, without BiH PDV, with place-of-supply notice. Exact legal wording confirmed by PROJEKTRA's accountant before the first real invoice. | 0.9 | n/a |

### 5.9 Tasks and agile

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-TASK-01 | Configurable issue types: epic, story, task, bug, sub-task, custom. | 1.0 | C |
| PRD-TASK-02 | Workflows as configurable state machines with a visual editor (engine in 5.14). | 1.0 | C |
| PRD-TASK-03 | Custom fields (text, number, date, select, user, multi-select) per project and issue type. | 1.0 | C |
| PRD-TASK-04 | Kanban and Scrum boards: backlog, sprints, story points, burndown, velocity, swimlanes, WIP limits. | 1.0 | C |
| PRD-TASK-05 | Links (blocks, duplicates, relates to), sub-tasks, mentions, comments, attachments, watchers. | 1.0 | C |
| PRD-TASK-06 | PQL (Projektrack Query Language) with autocomplete, saved filters, filters as dashboard widgets and exports. Keywords stay English; field names and values are localized. | 1.0 | C |
| PRD-TASK-07 | Bulk edit, keyboard shortcuts, inline editing. | 1.0 | C |
| PRD-TASK-08 | Every ticket can be linked to a work package; time on tickets flows into controlling. | 1.0 | C |

### 5.10 Service desk

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-SD-01 | Customer portal with request types and custom forms, login for portal users (free), own request overview, white-labeled per tenant. | 1.1 | B |
| PRD-SD-02 | Email to ticket (IMAP, Microsoft 365, webhook); replies land in the ticket. | 1.1 | B |
| PRD-SD-03 | SLAs with business-hour calendars, holidays, response and resolution times per priority and contract, escalations, pause on "waiting for customer". | 1.1 | B |
| PRD-SD-04 | Queues, assignment rules, canned responses and macros, internal notes vs. public replies. | 1.1 | B |
| PRD-SD-05 | Knowledge base suggestions while creating a request (ticket deflection). | 1.3 | B |
| PRD-SD-06 | CSAT survey; SLA reports per customer and contract. | 1.1 | B |
| PRD-SD-07 | Support quotas from the contract are consumed automatically. | 1.1 | B |
| PRD-SD-08 | ITSM processes (ITIL-aligned), each with its own workflow template: incident, service request, problem, change (risk assessment, change advisory approval), major incident; service catalog per customer. | 1.1 | B |
| PRD-SD-09 | Approvals: single, multi-step, parallel; by email link or portal; delegation when the approver is absent. | 1.1 | B |
| PRD-SD-10 | Service automation: auto-triage, assignment (round robin, least busy, skills, customer owner), SLA warnings and escalations, auto-replies, reminders, auto-close with notice, duplicate linking, follow-up tasks, quota alerts. | 1.1 | B |
| PRD-SD-11 | Assets light: customer systems linked to tickets and contracts. | L | B |

### 5.11 Knowledge management

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-KB-01 | Spaces per project, customer, team or global; permissions per space. | 1.3 | C |
| PRD-KB-02 | Page tree with drag and drop; block editor with headings, tables, code, callouts, task lists, images, embeds, table of contents; correct bidirectional text. | 1.3 | C |
| PRD-KB-03 | Macros: ticket list (PQL), project status, budget indicator, service report summary, child pages. | 1.3 | C |
| PRD-KB-04 | Versioning with diff and restore, comments including inline, mentions, page templates (meeting notes, decision log, runbook, project profile). | 1.3 | C |
| PRD-KB-05 | Export PDF/DOCX; import from Confluence (HTML/XML export) and Markdown. | 1.3 | C |
| PRD-KB-06 | Language variants per page (optional). | 1.3 | C |
| PRD-KB-07 | Real-time co-editing. | 2.0 | B |

### 5.12 Controlling and reporting

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-CTL-01 | Plan vs. actual per project, contract, work package: hours, cost, revenue, margin, percent complete, remaining budget. | 0.9 (basic), 1.2 (full) | C |
| PRD-CTL-02 | Earned value and estimate at completion (EAC), forecast. | 1.2 | B |
| PRD-CTL-03 | Utilization per employee and team: booked vs. target, billable vs. non-billable. | 1.2 | C |
| PRD-CTL-04 | Revenue and forecast reports: invoiced, approved but not invoiced, open. | 1.2 | B |
| PRD-CTL-05 | Project health indicator with configurable rules. | 0.9 (basic), 1.2 (rules) | C |
| PRD-CTL-06 | Dashboards with personal and shared widgets, report builder, saved reports, scheduled email delivery, XLSX/PDF export. | 1.2 | B |
| PRD-CTL-07 | Resource planning: capacity vs. plan per week, conflicts, absences, load histogram. | 1.2 | B |

### 5.13 Integrations and API

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-INT-01 | REST API (OpenAPI 3.1, generated docs), personal access tokens with scopes. | 0.9 | C |
| PRD-INT-02 | OAuth 2.0 app registration with fine-grained scopes; signed webhooks; API versioning and rate limits per app. | 1.0 | C |
| PRD-INT-03 | MCP server so external AI agents can book time, create tickets, query project status and generate service reports within the user's permissions. | 1.3 | B |
| PRD-INT-04 | Imports: Freedcamp (1.0, for PROJEKTRA's own move), Jira (CSV/JSON), Confluence, MS Project XML, spreadsheet service reports. | 1.0 / 1.3 | C |
| PRD-INT-05 | Calendar: ICS feed, CalDAV, Microsoft 365 and Google calendar connectors. | 1.3 | C (ICS), B |
| PRD-INT-06 | Nextcloud/Collabora document editing; SSO via OIDC (all editions from 1.0), SAML and SCIM (Enterprise). | 1.0 / L | C/E |
| PRD-INT-07 | Ecosystem plugins for WordPress and Moodle, server-side only, never exposing the instance URL. | 2.x | C/B |

### 5.14 Workflow and automation engine

One engine for tickets, service desk, projects, work packages, quotes, invoices, absences and roadmaps. No module builds its own automation.

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-WF-01 | Workflows as state machines per entity type and project: statuses grouped into To do / In progress / Done, transitions, conditions, validators, post-functions. | 1.0 | C |
| PRD-WF-02 | Visual workflow designer with simulation, versioning and safe migration of existing items. | 1.0 | C |
| PRD-WF-03 | Shipped workflow templates: simple task, software development, Scrum, Kanban, IT incident, service request, problem, change with approval, content production, quote approval, invoice approval, absence approval. | 1.0 / 1.1 | C/B |
| PRD-AUTO-01 | No-code rule builder with triggers: item created, field changed, status transitioned, comment added, SLA threshold, approval result, inbound email, incoming webhook, scheduled, manual button, time entry submitted, invoice overdue, budget threshold, absence approved. | 1.0 | C (event), B (all) |
| PRD-AUTO-02 | Conditions: field comparisons, PQL, user/role/group, customer/contract, business hours, if/else branches. | 1.0 | B |
| PRD-AUTO-03 | Actions: set or copy fields, transition, assign, create linked items or sub-tasks, clone, comment, email from template, in-app notification, outgoing webhook, add watcher, start approval, calendar entry, draft quote or invoice, audit note. Localized smart values. | 1.0 | C (simple), B |
| PRD-AUTO-04 | Delays and waits (business-calendar aware), branching per linked item, variables. Scope per project, customer or global. | 1.0 | B |
| PRD-AUTO-05 | Template library with ready-to-use rules (auto-assign, SLA escalation, customer reminder, auto-close, monthly maintenance ticket, quota alert). | 1.0 / 1.1 | B |
| PRD-AUTO-06 | Safety: loop detection, execution limits per rule and tenant, run log with inputs and outputs, test mode and dry run, enable/disable, ownership, audit trail, permission checks as rule owner or service actor. | 1.0 | C |
| PRD-AUTO-07 | Runs on the AMP profile via queue and cron; real-time when workers are available. | 1.0 | C |

### 5.15 Gantt and project planning

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-GANTT-01 | Interactive Gantt for work packages, tasks and milestones: drag to move and resize, inline editing, draw dependencies. | 1.0 | C |
| PRD-GANTT-02 | Dependency types FS, SS, FF, SF with lead and lag. | 1.0 | C |
| PRD-GANTT-03 | Auto-scheduling respecting working calendars, holidays and absences; manual scheduling per item. | 1.2 | B |
| PRD-GANTT-04 | Critical path, slack, baselines (plan vs. actual vs. forecast), progress from bookings or percent complete. | 1.2 | B |
| PRD-GANTT-05 | Resource assignment with load histogram and over-allocation warnings. | 1.2 | B |
| PRD-GANTT-06 | WBS summary rows, collapse/expand, zoom day to year, today line, filters, grouping. | 1.0 | C |
| PRD-GANTT-07 | Portfolio (multi-project) Gantt. | 1.2 | B |
| PRD-GANTT-08 | Export PDF/PNG, print with page setup, import MS Project XML. RTL rendering with mirrored time axis. | 1.0 / 1.3 | C |

### 5.16 Absence calendar

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-ABS-01 | Team calendar: vacation, sick leave, training, business travel, remote work, public holidays per country/region, company closures. | 0.9 (S), else 0.10 | C |
| PRD-ABS-02 | Views: personal, team, department, project team; month, quarter, year; availability heatmap. | 0.10 | C |
| PRD-ABS-03 | Requests and approvals through the workflow engine (configurable approvers, multi-step, delegation). Simple approval before the engine exists. | 0.10 / 1.0 | C |
| PRD-ABS-04 | Leave entitlements and balances per year with carry-over rules, half days, hours. | 0.10 | C |
| PRD-ABS-05 | Deputies for approvals, tickets and queues during absence; automation can reassign SLA-critical tickets. | 0.10 / 1.1 | C/B |
| PRD-ABS-06 | Conflict warnings: overlapping absences, absences against milestones and sprints. | 1.0 | C |
| PRD-ABS-07 | Absences reduce capacity in resource planning, Gantt auto-scheduling and sprint capacity. | 1.0 / 1.2 | C/B |
| PRD-ABS-08 | Out-of-office status on avatars, assignment pickers and mentions. | 0.10 | C |
| PRD-ABS-09 | Privacy: absence reasons (for example sick leave) visible only to authorized roles; others see "absent". | 0.10 | C |

### 5.17 Product management, views and templates

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-PM-01 | Hierarchy: product, goals, initiatives, epics, stories/tasks; releases with dates and release notes. | 1.2 | B |
| PRD-PM-02 | Roadmap views: timeline by month/quarter, Now / Next / Later, swimlanes; cross-team dependencies; shareable read-only roadmap link, white-labeled. | 1.2 | B |
| PRD-PM-03 | Ranked backlog, refinement status, estimation (points, t-shirt sizes, hours), prioritization frameworks RICE, WSJF, MoSCoW, value vs. effort. | 1.2 | B |
| PRD-PM-04 | Idea intake from portal and team, voting, links to epics, status feedback to requesters. | 1.2 | B |
| PRD-PM-05 | Release planning with capacity from team availability; burnup/burndown per release. | 1.2 | B |
| PRD-VIEW-01 | Views wherever meaningful: list, board, table, timeline, calendar, Gantt, roadmap, workload, dashboard. Savable per user and shareable, with filters, grouping, sorting, columns, swimlanes and presets. | 1.0 / 1.2 | C/B |
| PRD-TPL-01 | Template gallery: customer project fixed price, customer project time and material, managed services / retainer, IT service desk, Scrum, Kanban, product roadmap, website relaunch, LMS/Moodle migration, software rollout, event, employee onboarding, marketing campaign. Translatable, exportable/importable (JSON). | 0.9 (2 project templates), 1.0 (v1) | C |

### 5.18 Customer onboarding

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-ONB-01 | Workspace wizard: company and legal data, country (selects tax pack, holidays, templates), language (default `bs`), currency, time zone, working hours, logo and brand color with live preview, invoice numbering, VAT status. Every step skippable and resumable. | 0.9 | C |
| PRD-ONB-02 | Start empty, with removable sample data, or with import. | 0.9 | C |
| PRD-ONB-03 | "Getting started" checklist with progress: add team, define roles and rates, add first client, create first project, book first time entry, create first service report, issue first (e-)invoice. | 0.9 | C |
| PRD-ONB-04 | Client in one dialog including tax case preview ("Invoices to this client: reverse charge"). | 0.9 | C |
| PRD-ONB-05 | Client import from CSV/Excel with mapping wizard, duplicate detection and dry run. | 0.10 | C |
| PRD-ONB-06 | Invite client contacts to the portal with a branded welcome email. | 0.10 / 1.1 | C |
| PRD-ONB-07 | Team invitations by email, CSV, or SSO/SCIM; invitation shows role and projects. Per person: role, cost and sales rate, working time model, leave entitlement, deputy, languages, skills. | 0.9 | C |
| PRD-ONB-08 | Role-specific welcome flow on first login: profile, language, 2FA/passkey setup, 60-second guided tour per role. Reduced onboarding for light users and freelancers. | 0.9 | C |
| PRD-ONB-09 | Role templates with plain-language descriptions; permission matrix with explanations and risk hints; "view as this role/user" preview. Least privilege by default; financial fields hidden unless granted. | 0.9 | C |
| PRD-ONB-10 | Project wizard: from template, from accepted quote, by copy, or by import. Steps: client and contract, team, WBS, board and workflows, automation, wiki space, dates and milestones. Kickoff checklist per project. | 0.9 (template or blank), later steps per module | C |

### 5.19 In-app help and documentation

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-HELP-01 | Contextual "?" on every screen opens the matching help article in a side panel, offline-capable. | 1.0 | C |
| PRD-HELP-02 | Interactive tutorials (self-hosted tour engine, no third-party tracking). 0.9 ships tutorials for booking time and creating a service report. | 0.9 / 1.0 | C |
| PRD-HELP-03 | Empty states with one clear action; tooltips for advanced fields; shortcut overview on `?`. | 0.9 | C |
| PRD-HELP-04 | "What's new" panel per release with translated highlights. | 0.10 | C |
| PRD-HELP-05 | Documentation website, versioned per release: admin guide, user guides per role, tutorials, developer docs, release notes, upgrade guides. Screenshots generated automatically per language including RTL. | 1.0 | C |
| PRD-HELP-06 | 0.9 docs: installation guide (AMP, Docker), admin quick start, user quick starts for employee, project lead, controller in `bs`, `en`, `de`. | 0.9 | C |
| PRD-HELP-07 | Short scripted video tutorials recorded from demo data with subtitles; quick reference cards per role; training curricula. | 1.0+ | C |

### 5.20 PROJEKTRA as customer zero

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-ZERO-01 | One PROJEKTRA production instance with a tenant for PROJEKTRA IT Consulting and a separate demo tenant. It hosts the licensing module and the license portal. | 0.9 | n/a |
| PRD-ZERO-02 | Country pack BA out of the box: PDV 17 % domestic; services to foreign business customers per BiH place-of-supply rules with legal notice; BAM (domestic default) and EUR (EU customers); Bosnian documents for domestic, English for foreign customers. | 0.9 | C |
| PRD-ZERO-03 | Fiscalization check documented before PROJEKTRA's first real invoice; external fiscal system marker available. | 0.9 | n/a |
| PRD-ZERO-04 | PROJEKTRA uses from day one: clients, projects, contracts, time tracking, monthly service reports, invoices, licensing. | 0.9 | n/a |
| PRD-ZERO-05 | Product development planned in Projektrack from 1.0; support on the Projektrack service desk from 1.1; documentation in the wiki from 1.3. | 1.0 / 1.1 / 1.3 | n/a |
| PRD-ZERO-06 | Release rule: every release runs on the PROJEKTRA instance first, for at least 3 working days, before customers get it (security patches excepted). Issues found there are blockers. | 0.10+ (see roadmap note for 0.9) | n/a |
| PRD-ZERO-07 | PROJEKTRA business data never leaves the production instance; staging uses synthetic data only. | 0.9 | n/a |

### 5.21 United States readiness

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-US-01 | Regional formats independent of UI language. US default: MM/DD/YYYY, 12-hour clock, USD, `,` thousands separator, week starts Sunday, US Letter paper, miles, US address and phone formats. | 1.0 | C |
| PRD-US-02 | Sales tax instead of VAT: per line and jurisdiction (state, county, city), taxable and non-taxable items, exempt customers with certificate upload, nexus per state, inclusive or exclusive prices; adapter for automated sales tax services later. EIN on invoices. | 1.0 | C |
| PRD-US-03 | US invoicing conventions: Bill To / Ship To, PO number, Net terms, late fee text, ACH/wire/check remittance, statements of account, credit memos (same immutable logic as cancellation). | 1.0 | C |
| PRD-US-04 | Tenant payment links through provider adapters (card, ACH, PayPal; EU providers); payment status synced to the invoice. The tenant is the merchant. | 1.1 | B |
| PRD-US-05 | Accounting exports: CSV/IIF from 1.0; QuickBooks Online and Xero connectors later. Configurable fiscal year start. | 1.0 / L | C/B |
| PRD-US-06 | US federal and state holidays, PTO accrual and carry-over caps, overtime rules per jurisdiction, exempt / non-exempt flag, contractor flag. | 1.0 | C |
| PRD-US-07 | CCPA/CPRA handled by the GDPR tools; WCAG 2.2 AA covers Section 508 and ADA; security documentation package; SOC 2 readiness (Type I target 2027, Type II 2028). | 1.0+ | n/a |
| PRD-US-08 | SaaS data residency: EU region (default) and US region, chosen at signup; tenant data and backups never leave the region. | 1.1 | B/E |

### 5.22 AI native (optional, off by default)

AI is designed into every module through one shared layer. It is **optional and off by default**. Nothing is sent to any AI provider until a tenant admin enables it. The product is fully usable with AI disabled.

| ID | Requirement | Rel. | Ed. |
|---|---|---|---|
| PRD-AI-01 | Provider-agnostic adapter layer for cloud AI providers and self-hosted models. The tenant chooses provider and model per use case and can switch without losing rules, prompts or history. | 1.0 | C |
| PRD-AI-02 | The tenant's own API keys, stored encrypted; usage limits and cost caps per tenant, module and user; usage report for admins. | 1.0 | C |
| PRD-AI-03 | Data control: per module the admin decides which data may be sent; field-level permissions apply to AI exactly as to users; optional redaction of personal data. | 1.0 | C |
| PRD-AI-04 | Every AI call is audit-logged: who, which data categories, which provider and model, result, approval. | 1.0 | C |
| PRD-AI-05 | Prompts and AI templates are translatable strings, customizable per tenant; outputs in the user's or customer's document language. | 1.0 | C |
| PRD-AI-06 | Human approval: replies to customers, quotes, invoices, time bookings and changes with financial impact always need a person's approval. Low-risk internal actions (classify, tag, summarize, route) run automatically only when the admin allows it per rule. | 1.0 | C |
| PRD-AI-07 | AI actions in automation rules: classify, summarize, extract, draft, translate. Test mode and dry run apply. | 1.0 | B |
| PRD-AI-08 | Proposal drafts: quote lines, effort estimates and proposal text from a request or similar past projects. | 1.0 | B |
| PRD-AI-09 | Time assist: improve activity descriptions; booking suggestions from tickets and calendar. | 1.0 | C (basic) |
| PRD-AI-10 | Service desk: triage, categorization, routing, reply drafts, automatic replies with knowledge base answers for known request types. | 1.1 | B |
| PRD-AI-11 | Finance: incoming e-invoice and receipt extraction and coding, payment matching, dunning texts. | 1.1 | B |
| PRD-AI-12 | Controlling: budget and margin risk alerts, forecast explanations, plain-language questions turned into PQL queries and reports. | 1.2 | B |
| PRD-AI-13 | Knowledge: wiki summaries, search answers with linked sources. | 1.3 | B |
| PRD-AI-14 | Agents: multi-step tasks across modules via API and MCP server, within the user's permissions and with approval. | 2.0 | B |
| PRD-AI-15 | Every AI feature has a working non-AI path. Community on plain AMP works fully without any AI provider. | 1.0 | C |
| PRD-AI-16 | The 0.9 data model already contains what AI features need, so 1.0 adds them without breaking changes. | 0.9 | C |

---

## 6. Licensing and selling (product requirements)

| ID | Requirement | Rel. |
|---|---|---|
| PRD-LIC-01 | Licensing module on the PROJEKTRA instance: license customer (linked to CRM client), subscription, license (signed Ed25519 token with instance binding), activation, full change history. Built in-house. | 0.9 |
| PRD-LIC-02 | Direct B2B sale (channel 1): order or quote accepted, invoice issued with correct tax case, license issued as "pending payment" with 14-day grace, switched to active on payment, activation email with a readable activation code (format `PTK-XXXX-XXXX-XXXX`). | 0.9 |
| PRD-LIC-03 | Self-hosted online activation: Admin > License > Activate, enter code, done in under one minute. Daily check-in sends only instance ID, version and active user counts. | 0.9 |
| PRD-LIC-04 | Offline activation: upload a license file; signature validated locally. | 0.9 |
| PRD-LIC-05 | License screen: edition, users used vs. licensed (full and light), expiry, add-ons, last check-in, "What does my plan include?". | 0.9 |
| PRD-LIC-06 | License portal (basic in 0.9): subscriptions, invoices, license files, activations (move to a new server), renewal dates. | 0.9 |
| PRD-LIC-07 | Soft enforcement: over-count shows warnings, 30-day grace, then true-up. Never data loss, never lock-out of Community functions. On expiry, commercial features become read-only. | 0.9 |
| PRD-LIC-08 | Renewal reminders (60, 30, 7 days); automatic renewal and seat-change invoices; MRR/ARR, renewals and churn overview. | 0.10 |
| PRD-LIC-09 | Self-service checkout (channel 2) via a merchant of record behind an adapter; webhook-driven, idempotent license issue and refresh. | 1.0 |
| PRD-LIC-10 | 30-day Business trial from the installer. Not in 0.9. | 1.0 |
| PRD-LIC-11 | "Add users" button when the licensed count is reached; channel 1 sends a prefilled order request with 14-day grace. | 0.10 |
| PRD-LIC-12 | Partners order at partner price and assign licenses to customer instances; monthly consolidated invoices. | 2.0 |

---

## 7. Non-functional requirements

### 7.1 Security

| ID | Requirement |
|---|---|
| NFR-SEC-01 | OWASP ASVS Level 2 baseline for the whole product; Level 3 for authentication, session management, permissions and all finance functions. |
| NFR-SEC-02 | STRIDE threat model per module, updated when the module changes. |
| NFR-SEC-03 | Authentication: Argon2id, passkeys/WebAuthn, TOTP, 2FA enforced per role (default on for admins and finance roles), optional breached-password check, lockout and rate limiting, re-authentication for sensitive actions. |
| NFR-SEC-04 | Authorization: deny by default, policy on every action, field-level permissions, central row-level tenant isolation with tests that actively attempt cross-tenant access. |
| NFR-SEC-05 | Encryption in transit (TLS only, HSTS); application-level encryption at rest for secrets and sensitive fields; keys outside the database; documented rotation. |
| NFR-SEC-06 | Strict CSP with nonces, no inline scripts, CSRF protection, output encoding, SSRF protection, safe uploads, signed download URLs. |
| NFR-SEC-07 | Supply chain: lock files, dependency scanning, SAST, secret scanning, DAST in CI, SBOM (CycloneDX) and signed releases. |
| NFR-SEC-08 | GDPR tools (export, anonymization, deletion respecting legal retention); no telemetry without opt-in; no external calls by default. |
| NFR-SEC-09 | `security.txt`, disclosure process, advisories in the admin update screen; external penetration test before 1.0. Alignment with ISO/IEC 27001 and NIS2 logging. |
| NFR-SEC-10 | Synthetic data only in development, tests, seeds, screenshots and staging. |
| NFR-SEC-11 | No deletion of invoices, approved time or report snapshots; only cancellation, correction or new version. |

### 7.2 Invisible by default

Applies to every instance URL (self-hosted, SaaS, dedicated, partner, staging, demo) and everything it serves. It is a security requirement, enforced in code, tested in CI, and not switchable by any admin setting. The public product website and public docs site are separate properties.

| ID | Requirement |
|---|---|
| NFR-INV-01 | `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate` on every response: pages, redirects, error pages, API, downloads, assets. |
| NFR-INV-02 | Robots meta tag in every HTML page; `robots.txt` disallows everything for all crawlers. No sitemap, structured data, Open Graph or card tags. |
| NFR-INV-03 | Bundled web server configs set the same headers for static files. |
| NFR-INV-04 | `Referrer-Policy: no-referrer`; external links `rel="noopener noreferrer"`; no third-party scripts, fonts, analytics, CDNs or tracking; no link tracking or pixels in emails. |
| NFR-INV-05 | No product name, version, framework or generator before login (headers, meta, asset names, titles). Health endpoint returns only `OK`. |
| NFR-INV-06 | Error pages and API errors never echo internal URLs, hostnames, paths or stack traces. |
| NFR-INV-07 | SaaS: wildcard certificate and DNS; random tenant subdomains by default with optional readable alias. |
| NFR-INV-08 | Shared links: at least 128 bits of entropy, expiry (default 30 days), revocation, optional password, access logged, token never in referrer or plain-text logs. |
| NFR-INV-09 | Optional shielding: IP allowlist, hidden login path, VPN or zero-trust proxy, mutual TLS (Enterprise), crawler blocking. Dev, staging and demo require basic auth or IP allowlist by default. |
| NFR-INV-10 | CI crawl test fails the build on any violation; `projektrack doctor` checks live installations. |
| NFR-INV-11 | License check-in sends only instance ID and domain to PROJEKTRA, which treats instance domains as confidential. |

### 7.3 Performance

| ID | Requirement |
|---|---|
| NFR-PERF-01 | Interactions feel instant; optimistic UI. |
| NFR-PERF-02 | Page transitions p95 under 300 ms with 100,000 time entries and 50,000 tickets per tenant on the Docker reference setup. Verified from 1.0. |
| NFR-PERF-03 | First-load JS budget defined and enforced in CI. |
| NFR-PERF-04 | 0.9 sizing: fewer than 50 users per tenant, fewer than 10 tenants per instance. |
| NFR-PERF-05 | A minor update on the AMP profile completes in under 5 minutes without SSH access. |

### 7.4 Accessibility and UX

| ID | Requirement |
|---|---|
| NFR-UX-01 | WCAG 2.2 AA in LTR and RTL, light and dark mode. |
| NFR-UX-02 | Simple by default, powerful on demand (progressive disclosure). |
| NFR-UX-03 | Interaction budgets: book time in at most 3 interactions; service report in 1 click via preset; invoice from service report in 1 click plus confirmation; project from accepted quote in 1 click. |
| NFR-UX-04 | Undo instead of confirmation dialogs wherever an action is reversible. |
| NFR-UX-05 | Keyboard first: command palette, shortcuts for frequent actions, fully keyboard-operable timesheet. |
| NFR-UX-06 | Responsive for desktop, tablet, phone; installable PWA for time, approvals and tickets. |
| NFR-UX-07 | One design system with tokens; density setting (comfortable / compact). Default theme is the PROJEKTRA brand; white-label themes only swap tokens. |
| NFR-UX-08 | Calm, clear tone; verbs first on buttons; no jargon; no en dash character in any UI copy or generated document. |

### 7.5 Internationalization and RTL

| ID | Requirement |
|---|---|
| NFR-I18N-01 | Locales: `bs` (default, Latin script), `en` (technical source), `de`, `fr`, `es`, `ar` (RTL), `fa` (RTL). Hebrew is out of scope. |
| NFR-I18N-02 | No user-facing text in code. String identifiers `<component>:<key>`; each module ships its own language files. |
| NFR-I18N-03 | ICU MessageFormat with CLDR plural rules (Bosnian one/few/other, Arabic six forms). |
| NFR-I18N-04 | Fallback chain: user language, tenant language, `bs`, `en`. Raw keys never shown. |
| NFR-I18N-05 | Admin string customization per instance and tenant; overrides survive updates; changed source strings flagged. Optional inline translation mode. |
| NFR-I18N-06 | Language packs installable and updatable independently; community translation via a self-hostable platform; completeness shown in percent. |
| NFR-I18N-07 | RTL: full mirroring, logical CSS properties only, directional icons mirrored, bidi isolation for mixed content, RTL exports (PDF, DOCX, XLSX). |
| NFR-I18N-08 | Regional formats independent of UI language; native digits optional for `ar` and `fa`; Solar Hijri and Hijri display options; storage always Gregorian/UTC. |
| NFR-I18N-09 | Master data and documents are multilingual; documents use the customer's document language. |
| NFR-I18N-10 | CI fails on hard-coded strings, missing `en` or `bs` identifiers and physical CSS directions. Pseudo-localization and pseudo-RTL modes. |

### 7.6 AMP compatibility and installation

| ID | Requirement |
|---|---|
| NFR-AMP-01 | Runs fully on a plain AMP stack (Apache or Nginx, PHP, MySQL/MariaDB, cron) without Node, Composer, Redis or Docker on the server. |
| NFR-AMP-02 | Progressive infrastructure: Redis, a search engine, a PDF rendering service, WebSockets and S3 storage are detected and used when configured. Every optional service has a working fallback. |
| NFR-AMP-03 | Databases: MySQL, MariaDB and PostgreSQL supported and tested in CI. No vendor-specific SQL outside a tested adapter layer. |
| NFR-AMP-04 | Release ZIP with web installer (requirements check, database, admin, language, country pack, sample data); one cron entry. |
| NFR-AMP-05 | Docker Compose one-command installation; Helm chart for Kubernetes; SaaS; managed dedicated instances on PROJEKTRA servers. |
| NFR-AMP-06 | One-click update with pre-update check, automatic backup, signed release verification, maintenance mode, health check and automatic rollback. Release channels Stable, LTS, Preview. Optional automatic patch updates. |
| NFR-AMP-07 | Zero-data-loss migrations (expand/contract), tested from every supported previous version. |
| NFR-AMP-08 | No mandatory dependency on external SaaS, CDNs or Docker in Community. |

### 7.7 Configurability and white label

| ID | Requirement |
|---|---|
| NFR-CFG-01 | Configurable in the admin UI without code: rates and price lists, roles and permissions, service categories, numbering, tax rates and cases, country packs, rounding, working time models, holiday and SLA calendars, workflows, issue types, custom fields, document and email templates per language, branding, budget thresholds, mandatory booking fields, approval workflows, language settings, modules. |
| NFR-CFG-02 | Sensible defaults for an IT services company; setup wizard applies matching defaults. |
| NFR-WL-01 | White label per tenant and instance: name, logo, favicon, colors, login page, email sender, templates, PWA manifest, help and legal links. Brand appears only via the `{brand}` placeholder in strings. |
| NFR-WL-02 | Custom domains with automatic TLS. "Powered by Projektrack" removable where the edition allows. Partner view for several white-labeled instances. |

---

## 8. Editions and pricing summary

### 8.1 Editions

| Edition | License | Hosting | Content (summary) |
|---|---|---|---|
| Community | AGPL-3.0, free, unlimited users | Self-hosted (AMP, Docker, Kubernetes) | Core, onboarding, CRM, projects, contracts, WBS, time tracking, rates, service reports (all formats), quotes, invoicing with VAT and reverse charge, e-invoice creation, tasks/boards/backlog, configurable workflows, basic automation, absence calendar, basic Gantt, list/board/table/calendar views, wiki, basic reports, REST API, all languages including RTL, AI provider layer with basic assists (off by default). |
| Business | Proprietary, per user | SaaS or self-hosted with license key | Community plus full automation engine, service desk with ITSM and SLAs, incoming e-invoices, advanced Gantt, resource planning, product roadmaps and prioritization, advanced controlling, customer sign-off workflows, DATEV export, dunning, MCP server, advanced dashboards, AI actions, AI proposal drafts, AI service desk, finance and controlling assists (off by default), standard support. |
| Enterprise | Proprietary, per user | SaaS, dedicated SaaS, self-hosted | Business plus SAML/SCIM, multi-company console, full white label, advanced audit, data residency options, high availability, priority support with SLA. |
| Partner | Proprietary | Partner-managed | Resale and white-label rights, partner console, partner discount. |

Billed: active Full and Light users. Free: Portal users, deactivated users, API/service accounts. Same per-user price for SaaS and self-hosted.

### 8.2 Default price list (prices are configurable data; final decision by PROJEKTRA)

| Plan | Yearly billing (per user/month) | Monthly billing (per user/month) | Notes |
|---|---|---|---|
| Community | 0 EUR | 0 EUR | Self-hosted, unlimited users |
| Cloud Free | 0 EUR | 0 EUR | SaaS, up to 3 users, Community features |
| Business: Full user | 14.90 EUR | 17.90 EUR | All Business modules |
| Business: Light user | 4.90 EUR | 5.90 EUR | Time, expenses, own tasks, approvals |
| Enterprise: Full user | 29.90 EUR | 35.90 EUR | Minimum 25 users |
| Enterprise: Light user | 9.90 EUR | 11.90 EUR | |
| Portal users | free | free | Unlimited |
| White label for Business | 149 EUR per instance/month | | Included in Enterprise |
| Dedicated hosting / custom domain on PROJEKTRA servers | from 99 EUR per instance/month | | Plus user licenses |

USD list (yearly / monthly, per user/month): Business full 16.90 / 19.90 USD, light 5.90 / 6.90 USD; Enterprise full 34.90 / 41.90 USD, light 11.90 / 13.90 USD; white label 169 USD per instance/month; dedicated hosting from 119 USD per instance/month.

Pricing principles: regional price lists (for example a reduced list for the Western Balkans); no promotional first-year rates; discounts only for yearly or multi-year terms, nonprofits, education and partners (default partner discount 25 %).

**Founding Customer plan** (first customers from 1 December 2026): Business edition, price locked for 24 months, onboarding package included, direct roadmap influence. The first buyer is expected to be Learnteq through direct sale (invoice and bank transfer), delivered as a managed dedicated instance on PROJEKTRA servers.

---

## 9. Release scope summary

Dates and details are in `roadmap.md`.

| Release | Date | Theme | Scope summary |
|---|---|---|---|
| 0.9 Founding Edition | 01.12.2026 | Run projects, prove work, get paid | Foundation and security, i18n with `bs`/`en`/`de` complete, AMP ZIP and Docker installation, onboarding wizards, CRM, projects, contracts, WBS, time tracking with approval and period lock, rate hierarchy, one-click service reports, invoicing with tax engine (DE, BA), e-invoice creation and validation, licensing and direct sale, PROJEKTRA instance live, one-click update, invisible by default. Should: quotes with XLSX costing, basic absence calendar, expenses. |
| 0.10 | 18.12.2026 (to be replanned after the staged 0.9 delivery, decision C-06) | Complete the money flow | Quotes with online acceptance, absence calendar, expenses, incoming e-invoices, payments and dunning, `fr`/`es` reviewed. |
| 1.0 GA | 01.03.2027 | Work management | Tasks, boards, sprints, PQL, workflow designer and automation engine, self-service checkout, US country pack, Freedcamp import, views, template gallery, basic Gantt, `ar`/`fa` complete, AI provider layer, AI automation actions, AI proposal drafts, AI time assist, SaaS self-signup, Community open source, docs website, external pen test. |
| 1.1 | 30.04.2027 | Service management and US region | US data region, tenant payment links, service desk with portal, SLAs, ITSM, approvals, CSAT, AI ticket triage and replies, AI finance assists. |
| 1.2 | 30.06.2027 | Plan and steer | Advanced Gantt, resource planning, product roadmaps, prioritization, idea portal, advanced controlling, dashboards, AI controlling. |
| 1.3 | 30.09.2027 | Knowledge and integrations | Wiki, MCP server, imports, DATEV, calendar sync, Peppol preparation, AI knowledge features. |
| 2.0 | Q4 2027 | Ecosystem | Plugin SDK and marketplace, partner console, co-editing, Peppol, BiH fiscalization, AI agents, mobile apps. |
| 2.x | 2028 | Ecosystem plugins | Projektrack for WordPress, Projektrack for Moodle. |

---

## 10. Success metrics

| ID | Metric | Target | Measured |
|---|---|---|---|
| MET-01 | Time to first value after installation (company set up, first project, first time entry) | Under 10 minutes | Moderated test and onboarding checklist timestamps, every milestone |
| MET-02 | System Usability Scale (SUS) | 85 or higher before 1.0 | Usability test per milestone |
| MET-03 | Task success rate for core tasks (5 participants, moderated) | At least 90 % | Every milestone, report per release |
| MET-04 | Time to book a full week on three work packages | Under 2 minutes for a returning user | Usability test |
| MET-05 | Service report creation | 1 click via preset | UX review |
| MET-06 | License activation (self-hosted, online) | Under 1 minute | Release test |
| MET-07 | Minor update on AMP profile | Under 5 minutes, no SSH | Release test |
| MET-08 | E-invoice validation errors on finalized invoices | 0 | CI and production validation logs |
| MET-09 | Invisible-by-default crawl test | 100 % of responses compliant | CI, every build |
| MET-10 | First customer license | Sold and delivered on 01.12.2026 | Release 0.9 |
| MET-11 | Translation completeness | `bs`, `en`, `de` 100 % every release; all seven locales 100 % from 1.0 | CI report |

---

## 11. Out of scope

- Hebrew locale (`he`): never shipped, offered or listed.
- Cyrillic Bosnian (`bs-Cyrl`) in 0.9.
- In 0.9: tickets and service desk, automation engine, Gantt, roadmaps, wiki, AI features, SaaS self-signup, 30-day trial, self-service checkout.
- A microservice architecture. Projektrack is a modular monolith.
- Payroll and full HR. Only HR-light (working time models, leave, overtime balance).
- A full ERP or general ledger. Accounting is reached through exports and connectors.
- Native mobile apps before 2.0 (PWA covers mobile before then).
- Any mandatory external service in Community (no required CDN, SaaS dependency, Docker or AI provider).
- Telemetry without explicit opt-in.
- Any setting that makes an instance indexable.
- Features outside the current milestone: they go to `../backlog.md`.

---

## 12. Constraints and hard rules (product level)

- No floating point for money; no time calculation without a time zone.
- No deletion of invoices, approved time or report snapshots.
- No function without a permission check; no query without tenant scope.
- No user-facing text in code; every string translatable and customizable.
- AI is optional and off by default; no AI output reaches a customer or changes financial data without a person's approval.
- The date 01.12.2026 is fixed. Scope is the variable, never quality or security.
