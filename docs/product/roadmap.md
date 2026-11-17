# Release roadmap

Status as of 16.11.2026. Requirement details are in `PRD.md`. Ideas outside the current milestone go to `../backlog.md`.

The launch date of 1 December 2026 is fixed. **Scope is the variable, never quality or security.** The date is protected by cutting scope early, not by skipping tests, reviews or tenant isolation.

From release 1.2 the roadmap is maintained inside Projektrack and published as a shareable roadmap view for customers.

---

## 1. Overview

| Release | Target date | Theme | Content |
|---|---|---|---|
| **0.9 Founding Edition** | 01.12.2026 (Tuesday) | Run projects, prove work, get paid | See section 2. |
| **0.10** | 18.12.2026 (to be replanned, see C-06) | Complete the money flow | Quotes with XLSX costing and online acceptance; absence calendar and out-of-office planner; expenses; incoming e-invoices (upload, inbound mailbox, readable view, project cost assignment); payments and dunning; `fr` and `es` reviewed. |
| **1.0 General Availability** | 01.03.2027 | Work management | Tasks and issues, boards, backlog, sprints, PQL; **workflow designer and automation engine**; self-service license checkout via a merchant of record; **US country pack** (en-US formats, sales tax, US invoicing conventions, US holidays, USD price list); Freedcamp import for PROJEKTRA; views (list, board, table, calendar, timeline); template gallery v1; basic Gantt; `ar` and `fa` complete with RTL exports; **AI provider layer (optional, off by default), AI actions in automation rules, AI proposal drafts, AI time assist**; SaaS self-signup with billing and custom domains; Community edition published as open source; documentation website complete; external penetration test. |
| **1.1** | 30.04.2027 | Service management and US region | SaaS US data region; payment links for tenants (card and wallet providers); service desk with customer portal; email to ticket; SLAs; ITSM processes (incident, request, problem, change); approvals; service automation templates; CSAT; quota consumption; **AI ticket triage, reply drafts and automatic replies; AI finance assists (invoice extraction, payment matching, dunning texts)**. |
| **1.2** | 30.06.2027 | Plan and steer | Advanced Gantt (critical path, baselines, auto-scheduling, portfolio); resource planning with absences; **product roadmaps** (timeline, Now / Next / Later, releases); prioritization (RICE, WSJF, MoSCoW); idea portal; advanced controlling (EAC, forecast); dashboards and report builder; **AI controlling (risk alerts, forecast explanations, plain-language reports)**. |
| **1.3** | 30.09.2027 | Knowledge and integrations | Wiki and knowledge base; MCP server; imports (Jira, Confluence, Freedcamp, MS Project); DATEV export; calendar sync (CalDAV, Microsoft 365, Google); Peppol preparation; **AI knowledge features (wiki summaries, search answers with linked sources)**. |
| **2.0** | Q4 2027 | Ecosystem | Public plugin SDK and marketplace; partner console; real-time co-editing; Peppol; fiscalization for Bosnia and Herzegovina; **AI agents for multi-step tasks across modules (with approval)**; mobile apps. |
| **2.x** | 2028 | Ecosystem plugins | Projektrack for WordPress; Projektrack for Moodle. |

---

## 2. Release 0.9 "Founding Edition" (01.12.2026)

The first customer license is sold and delivered on 1 December 2026. The first license is delivered as a **managed dedicated instance on PROJEKTRA servers**. The AMP ZIP and Docker paths still ship in 0.9, but the managed instance is the delivery critical path.

**Decision of 17.11.2026 (C-06): early access plus staged delivery.** The Must scope below is about 4.7 to 6 times the capacity of the day plan. On 1 December the license is sold and the managed instance is delivered with a "0.9.0 Early Access" build that contains only work that passed the full Definition of Done. The rest of 0.9 follows in staged releases:

| Release | Content | Target |
|---|---|---|
| 0.9.0 Early Access | Signed license, managed instance, finished foundation work | 01.12.2026 |
| 0.9.1 | Foundation complete (M-01, M-02, M-03, M-11, update by CLI) | early December 2026 |
| 0.9.2 | Core work and service reports (M-05, M-06) | about 22.12.2026 |
| 0.9.3 | Invoicing, tax, licensing, customer zero (M-07 without e-invoicing, M-08, M-09) | January 2027 |
| 0.9.4 | E-invoicing, onboarding, docs (rest of M-07, M-04, M-12) | about end of January 2027 |

Targets are recalibrated on 21.11.2026. Twelve low-risk simplifications are applied (for example one-click update UI, AMP web installer, license portal and passkeys move to 0.9.x or 0.10); the full list is in `docs/stories/0.9/README.md`. Security, tenant isolation, tests and legal correctness of invoices are not cut. The Should items in 2.2 move out of 0.9.

### 2.1 Must have (release blockers)

| # | Area | Scope |
|---|---|---|
| M-01 | Foundation | Authentication with 2FA and passkeys, tenants, roles and permissions, module registry, audit log, security baseline in CI, Projektrack brand theme, light and dark mode. |
| M-02 | i18n | Full string system and admin string customization. `bs`, `en`, `de` complete. `fr`, `es`, `ar`, `fa` machine-prefilled and labeled "preview". RTL layout working (logical CSS from day one). |
| M-03 | Installation | AMP release ZIP with web installer and Docker Compose, both tested on a fresh server. Signed release. |
| M-04 | Onboarding | Workspace wizard, client onboarding, team invitations with role templates, project wizard (from template or blank), "Getting started" checklist. |
| M-05 | Core work | Customers, projects, contracts, WBS and work packages, time tracking (weekly timesheet, timer, approval, period lock), rate hierarchy. |
| M-06 | Service reports | One-click service reports in DOCX, PDF and XLSX with formulas. |
| M-07 | Invoicing | Invoicing with VAT, reverse charge and exemptions (country packs DE and BA), gapless numbering, cancellation, **e-invoice creation (ZUGFeRD and XRechnung) with validation and email sending**. |
| M-08 | Licensing | Licensing module on the PROJEKTRA instance; signed license with online activation code and offline license file; license screen; basic license portal; Founding Customer plan; direct sale flow (order, invoice, pending-payment grace, activation email). |
| M-09 | Customer zero | PROJEKTRA instance live with the Bosnia and Herzegovina country pack. |
| M-10 | Updates | One-click update with pre-update check, automatic backup and rollback, so 0.9 customers can move to 0.10 without help. |
| M-11 | Invisible by default | `noindex` headers and meta tags on every response, `robots.txt` disallow all, no-referrer policy, no fingerprint, unguessable shared links, CI crawl test. |
| M-12 | Docs | Installation guide (AMP, Docker), admin quick start, user quick starts per role (employee, project lead, controller) in `bs`, `en`, `de`. In-app "Getting started" tutorial for booking time and creating a service report. |

### 2.2 Should have (only if on schedule by 26.11.2026, otherwise 0.10)

| # | Scope |
|---|---|
| S-01 | Quotes with XLSX costing. |
| S-02 | Absence calendar (basic). |
| S-03 | Expenses. |

### 2.3 Explicitly not in 0.9

| Item | Note |
|---|---|
| Tickets and service desk | Tasks in 1.0, service desk in 1.1. |
| Automation engine | 1.0. |
| Gantt | Basic in 1.0, advanced in 1.2. |
| Roadmaps | 1.2. |
| Wiki | 1.3. |
| AI features | The data model and the AI architecture decision prepare them; first features in 1.0. |
| SaaS self-signup | 1.0. The first customer gets a managed instance on PROJEKTRA servers. |
| 30-day Business trial and self-service checkout | 1.0. Direct sale only in 0.9. |

### 2.4 Day plan

| Dates (2026) | Sprint | Goal |
|---|---|---|
| Mon 17.11. | Phase 0 | Specification, product and architecture documents, architecture decisions, data model, stories for 0.9; stack and repository running. |
| Tue 18.11. to Fri 21.11. | L1 Foundation | Auth, tenants, roles, modules, i18n, brand theme, installer (AMP and Docker), CI matrix. |
| Sat 22.11. to Wed 25.11. | L2 Core | Customers, projects, contracts, WBS, time tracking, approvals, rates, service reports. |
| Thu 26.11. to Sat 28.11. | L3 Money and onboarding | Invoicing, tax engine, e-invoicing, licensing, onboarding wizards, checklist. **Feature freeze Sat 28.11. at 18:00.** |
| Sun 29.11. to Mon 30.11. | Hardening | Independent security review, cross-database and AMP tests, performance check, demo data, docs and tutorials, release candidate, install test on fresh servers. **Go/no-go Mon 30.11. at 16:00.** |
| Tue 01.12. | Delivery | Release 0.9.0 Early Access signed, instance provisioned, license key issued, onboarding session with the first customer and walkthrough of the staged release plan. |

Every evening a short status goes to the product owner: done, risks, proposed scope cuts. If a Must item is at risk, the cut or simplification is proposed immediately, with impact.

### 2.5 Release rule for 0.9

The customer-zero rule asks that every release runs on the PROJEKTRA instance for at least 3 working days before customers get it. The 0.9 day plan leaves no such window (go/no-go on 30.11., delivery on 01.12.). Proposed handling, to be confirmed by the product owner: for 0.9 the PROJEKTRA instance runs the release candidate from the start of hardening (29.11.), and the full 3-day rule applies from 0.10.

---

## 3. AI native: release mapping

AI is **optional and off by default**. No data reaches an AI provider until a tenant admin enables it. Customer-facing or financial output always needs a person's approval. Every AI feature has a working non-AI path.

| Release | AI scope |
|---|---|
| 0.9 | None in the product. The data model and the AI architecture decision (provider layer, data control, approval rules) are in place so 1.0 needs no breaking change. |
| 1.0 | **Provider layer** (provider-agnostic, cloud and self-hosted models, tenant keys, cost caps, audit, data control); **AI actions in automation rules** (classify, summarize, extract, draft, translate); **proposal drafts** (quote lines, effort estimates, proposal text); **time assist** (better activity descriptions, booking suggestions from tickets and calendar). |
| 1.1 | **Service desk:** ticket triage, categorization, routing, reply drafts, automatic replies with knowledge base answers for known request types. **Finance:** incoming e-invoice and receipt extraction and coding, payment matching, dunning texts. |
| 1.2 | **Controlling:** budget and margin risk alerts, forecast explanations, plain-language questions turned into PQL queries and reports. |
| 1.3 | **Knowledge:** wiki summaries, search answers with linked sources. |
| 2.0 | **Agents:** multi-step tasks across modules via API and MCP server, within the user's permissions and with approval. |

---

## 4. Customer-zero milestones (PROJEKTRA internal use)

| Release | PROJEKTRA uses |
|---|---|
| 0.9 | Clients, projects, contracts, time tracking, monthly service reports, invoices, licensing and license customers. |
| 1.0 | Projektrack's own development (backlog, sprints, roadmap, releases), moved in via the Freedcamp import. |
| 1.1 | Customer support for license customers on the Projektrack service desk (portal, SLAs per plan). |
| 1.2 | Roadmap maintained in Projektrack and shared with customers. |
| 1.3 | Product documentation and internal knowledge in the Projektrack wiki. |

---

## 5. Working rules for every release

- Every release ends with a running, demonstrable version.
- Stories with acceptance criteria are written per release before implementation.
- Money, tax and time logic is built test first, including edge cases (rounding, rate change mid-month, cancellation, reverse charge, mixed tax rates, leap year, daylight saving time change, currency conversion).
- A moderated usability test with 5 participants closes each milestone. Task success target at least 90 %; SUS target 85 or higher before 1.0.
- Each release completes a demo script, updated demo data and green quality gates.
