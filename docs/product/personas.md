# Personas

Eight personas guide design, onboarding tours, role templates and usability tests. Names are fictional. Each persona maps to a role template in the product.

| Persona | Role template | License type |
|---|---|---|
| Employee | Employee | Full or Light user |
| Project lead | Project Lead | Full user |
| Controller / finance | Controller | Full user |
| Service agent | Service Agent | Full user |
| Product owner | Product Owner | Full user |
| Admin | Admin | Full user |
| Client portal user | Client (Customer) | Portal user (free) |
| Freelancer | External/Freelancer | Light user |

---

## 1. Employee ("Amra, consultant")

- **Context:** Works on three to five projects at once. Books time daily or at the end of the week. Uses Bosnian or German UI.
- **Goals:** Book time fast and correctly. Request leave without email ping-pong. See own tasks for today.
- **Frequent tasks:** Fill the weekly timesheet; start and stop a timer from a ticket; submit the week; request vacation; record expenses; work on assigned tasks.
- **Pains:** Searching for the right work package; rejected weeks without a clear reason; booking the same stand-up every day; mobile booking that loses data offline.
- **Key screens:** Weekly timesheet, timer, command palette quick entry, My work, absence request, expense entry, mobile PWA.
- **Success:** Books a full week on three work packages in under 2 minutes, without documentation.

## 2. Project lead ("Mirza, senior IT project lead")

- **Context:** Runs customer projects with contracts of different types. Reports to customers monthly.
- **Goals:** Keep budget and schedule under control. Deliver correct service reports on time. Spot risks early.
- **Frequent tasks:** Create projects from templates or accepted quotes; maintain the WBS; approve team time; generate monthly service reports; send reports for customer sign-off; check budget consumption; plan in the Gantt chart (from 1.0).
- **Pains:** Building reports by hand in spreadsheets; late or incomplete bookings; budget surprises at month end; unclear rates.
- **Key screens:** Project overview with health indicator, WBS editor, approval inbox, service report presets, contract budget view, Gantt, project dashboard.
- **Success:** Previous-month service report for a whole project in one click, totals correct in every format.

## 3. Controller / finance ("Lejla, controller")

- **Context:** Owns periods, invoices, tax and exports. Works with an external accountant. Needs audit-proof records.
- **Goals:** Correct invoices with the right tax case. Gapless numbering. Valid e-invoices. Clean month-end close.
- **Frequent tasks:** Lock the month; create invoices from approved service reports; check tax case and reason; issue ZUGFeRD or XRechnung; cancel and re-issue; record payments; run dunning; export for accounting; process incoming e-invoices.
- **Pains:** Manual tax decisions; XML errors found by the customer; numbering gaps; rate changes that alter history; missing Leitweg-ID discovered too late.
- **Key screens:** Period close, invoice list and editor, tax case panel, e-invoice validation result, open items, dunning, incoming invoice inbox, exports.
- **Success:** Finalizes a reverse charge e-invoice that validates first time, with no manual tax lookup.

## 4. Service agent ("Tarik, support agent")

- **Context:** Works tickets from several customers with different SLAs (from 1.1).
- **Goals:** Know what is urgent. Reply quickly. Never breach an SLA unnoticed. Book time on tickets without extra steps.
- **Frequent tasks:** Work the queue; reply publicly or add internal notes; use canned responses; escalate; link duplicates; hand over to a deputy; book time from the ticket timer.
- **Pains:** Hidden SLA clocks; tickets lost during absence; repeated questions that a knowledge article answers.
- **Key screens:** Queue view, ticket detail with SLA timer, reply editor, macros, knowledge suggestions, personal dashboard.
- **Success:** Every SLA-critical ticket is visible and handled before breach; time booked on tickets reaches the contract automatically.

## 5. Product owner ("Selma, product owner")

- **Context:** Owns a product backlog and roadmap; works with one or more delivery teams (from 1.0, roadmaps from 1.2).
- **Goals:** Prioritize with clear criteria. Plan releases against real capacity. Share a roadmap without exposing internal data.
- **Frequent tasks:** Refine and rank the backlog; score with RICE or WSJF; plan sprints and releases; review ideas from customers; publish a read-only roadmap link.
- **Pains:** Roadmaps in slides that go stale; capacity that ignores absences; ideas scattered across email.
- **Key screens:** Backlog, sprint board, roadmap timeline, Now / Next / Later, idea portal, release view.
- **Success:** Release plan reflects team availability and is shared with customers in one link.

## 6. Admin ("Damir, IT admin")

- **Context:** Installs and runs Projektrack on AMP hosting, Docker or a managed instance. Responsible for security and users.
- **Goals:** Safe installation and updates. Least-privilege permissions. License applied without support. Instance never exposed.
- **Frequent tasks:** Run the installer or setup wizard; invite users; assign role templates; check "view as role"; enable modules; customize strings; apply a license; run one-click updates; review the audit log; enable AI (optional) with data controls.
- **Pains:** Updates that break; permission sprawl; license tickets; tools that leak URLs or call third parties.
- **Key screens:** Setup wizard, users and roles, permission matrix, modules, language customization, license screen, update screen, audit log, security and doctor report, AI settings.
- **Success:** Minor update in under 5 minutes without SSH; license activation in under 1 minute.

## 7. Client portal user ("Ms. Weber, customer project manager")

- **Context:** Works at the customer. Uses Projektrack only through the portal or a signed link. Free user.
- **Goals:** Follow own requests and projects. Approve service reports quickly. Find invoices.
- **Frequent tasks:** Open a sign-off link and approve or comment on a service report; accept a quote online; raise a request (from 1.1); view invoices and documents.
- **Pains:** Too many logins; seeing internal noise; unclear status.
- **Key screens:** Portal home, request form, request list, service report sign-off page, quote acceptance page, invoice list.
- **Success:** Approves a service report from an email link in under one minute, in her own language.

## 8. Freelancer ("Nedim, external developer")

- **Context:** External contributor on one or two projects. Light user. Contractor flag set.
- **Goals:** Book time and expenses with minimum effort. See only assigned work.
- **Frequent tasks:** Book time on assigned work packages or tickets; submit the week; add expenses; update own tasks.
- **Pains:** Full-size tools with menus he never needs; seeing data he should not see.
- **Key screens:** Reduced navigation, weekly timesheet, own tasks, expense entry.
- **Success:** Productive after a short focused onboarding; sees no rates, costs or other customers.
