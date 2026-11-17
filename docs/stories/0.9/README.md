# Release 0.9 "Founding Edition": stories and capacity check

Delivery date: Tuesday, 1 December 2026. Status of this document: 17.11.2026, scope decision C-06 recorded (see "Decision of 17.11.2026" below).

Scope sources: the 0.9 Must and Should lists of the release plan, confirmed decisions C-01 to C-05 and assumptions A-01 to A-12 in `docs/decisions/assumptions.md`. Relevant for scope:

- **C-03**: the first customer is delivered as a managed dedicated instance on PROJEKTRA servers. The AMP web installer stays Must, but it is not on the delivery critical path.
- **A-11**: no 30-day trial and no self-service checkout in 0.9; licensing uses channel 1 (direct sale, invoice and bank transfer) only.
- Not in 0.9: tickets and service desk, automation engine, Gantt, roadmaps, wiki, AI product features, SaaS self-signup, incoming e-invoices (0.10).

## How to read the stories

- One file per epic (E01 to E15). Each story has ID, title, persona, user story, Gherkin acceptance criteria, priority, estimate, sprint, dependencies, modules, edition and required reviewers.
- **Estimates are ideal developer hours** and include implementation, automated tests (Pest, Vitest, Playwright; full suite on MySQL, MariaDB and PostgreSQL and on the AMP profile), review fixes and docs, that is, the full Definition of Done. Each story shows the split build / tests / review and docs.
- Estimates were made bottom-up per story and were **not** adjusted to fit the calendar.
- Reviewers: `finance-tax` for anything involving money, tax or invoices; `security` for auth, permissions, isolation, files and licensing; `ux` for every screen; `qa` for every story.

Sprints:

| Sprint | Dates (2026) | Calendar days |
|---|---|---|
| L1 Foundation | 18-21 Nov | 4 |
| L2 Core | 22-25 Nov | 4 |
| L3 Money and onboarding | 26-28 Nov (feature freeze Sat 28 Nov 18:00) | 3 |
| Hardening | 29-30 Nov (go/no-go Mon 30 Nov 16:00) | 2 |
| Total | | 13 |

Phase 0 (17 Nov) and Delivery day (1 Dec) are not counted as build capacity.

## Story index

| ID | Title | Epic | Priority | Estimate (h) | Sprint |
|---|---|---|---|---|---|
| S-0901 | Module registry with runtime enable/disable | E01 | Must | 16 | L1 |
| S-0902 | Multi-tenancy with central tenant scope | E01 | Must | 18 | L1 |
| S-0903 | Authentication and session security | E01 | Must | 14 | L1 |
| S-0904 | TOTP two-factor authentication enforced per role | E01 | Must | 10 | L1 |
| S-0905 | Passkeys (WebAuthn) | E01 | Must | 12 | L1 |
| S-0906 | Roles, project roles and field-level permissions | E01 | Must | 22 | L1 |
| S-0907 | Tamper-evident audit log | E01 | Must | 10 | L1 |
| S-0908 | CI quality and security pipeline with database matrix | E01 | Must | 16 | L1 |
| S-0909 | Web security baseline | E01 | Must | 12 | L1 |
| S-0910 | Brand theme, light/dark mode and app shell | E01 | Must | 22 | L1 |
| S-0911 | Central brand and legal entity configuration | E01 | Must | 6 | L1 |
| S-0912 | Settings framework and notifications | E01 | Must | 12 | L1 |
| S-0913 | REST API foundation with OpenAPI | E01 | Must | 10 | L1 |
| S-0914 | Command palette and global search | E01 | Should | 10 | L2 |
| S-0915 | Independent security review and threat models | E01 | Must | 18 | Hardening |
| S-0916 | String system with ICU MessageFormat and fallback chain | E02 | Must | 18 | L1 |
| S-0917 | i18n quality gates and pseudo-localization | E02 | Must | 10 | L1 |
| S-0918 | Admin language customization | E02 | Must | 14 | L1 |
| S-0919 | Locale formats and time zones | E02 | Must | 8 | L1 |
| S-0920 | RTL layout foundation | E02 | Must | 14 | L1 |
| S-0921 | Language packs and preview languages | E02 | Must | 10 | L1 |
| S-0922 | Complete bs, en and de for all 0.9 strings | E02 | Must | 16 | Hardening |
| S-0923 | Docker Compose production stack (managed instance profile) | E03 | Must | 14 | L1 |
| S-0924 | Signed release pipeline (ZIP, images, checksums, SBOM) | E03 | Must | 12 | L1 |
| S-0925 | AMP release ZIP with web installer and fallbacks | E03 | Must | 22 | L1 |
| S-0926 | Docker one-command installer script | E03 | Must | 8 | L1 |
| S-0927 | Operations CLI: install, update, backup, restore | E03 | Must | 14 | L2 |
| S-0928 | One-click update with pre-update check, backup and rollback | E03 | Must | 26 | L3 |
| S-0929 | Fresh-server install tests, upgrade test and release candidate | E03 | Must | 14 | Hardening |
| S-0930 | Workspace wizard | E04 | Must | 16 | L3 |
| S-0931 | Team invitations with role templates | E04 | Must | 12 | L3 |
| S-0932 | Permission matrix editor with explanations | E04 | Must | 12 | L3 |
| S-0933 | "View as this role/user" preview | E04 | Should | 8 | L3 |
| S-0934 | Role-based welcome flow on first login | E04 | Must | 10 | L3 |
| S-0935 | Project wizard with templates and kickoff checklist | E04 | Must | 18 | L3 |
| S-0936 | "Getting started" checklist | E04 | Must | 8 | L3 |
| S-0937 | Sample data option and synthetic demo dataset | E04 | Must | 14 | Hardening |
| S-0938 | Client and contact import wizard (CSV/Excel) | E04 | Should | 12 | L3 |
| S-0939 | Clients and contacts with one-dialog onboarding and tax case preview | E05 | Must | 16 | L2 |
| S-0940 | Projects with team and project roles | E05 | Must | 14 | L2 |
| S-0941 | Contracts with budgets, role quotas and alerts | E05 | Must | 16 | L2 |
| S-0942 | Work breakdown structure and service categories | E05 | Must | 18 | L2 |
| S-0943 | Client activity timeline | E05 | Should | 6 | L2 |
| S-0944 | Time entry domain, validation, rounding and roll-up | E06 | Must | 18 | L2 |
| S-0945 | Weekly timesheet grid | E06 | Must | 20 | L2 |
| S-0946 | Timer and day view | E06 | Must | 10 | L2 |
| S-0947 | Approval workflow with locking and correction entries | E06 | Must | 16 | L2 |
| S-0948 | Monthly period close | E06 | Must | 6 | L2 |
| S-0949 | Recurring booking proposals | E06 | Should | 10 | L2 |
| S-0950 | Rate hierarchy and price lists with validity periods | E07 | Must | 22 | L2 |
| S-0951 | Currencies and stored exchange rates | E07 | Must | 8 | L2 |
| S-0952 | Surcharges (weekend, public holiday, night, on-call) | E07 | Should | 8 | L2 |
| S-0953 | Service report engine with presets and totals | E08 | Must | 20 | L2 |
| S-0954 | PDF export (LTR and RTL) | E08 | Must | 10 | L2 |
| S-0955 | DOCX export and template management | E08 | Must | 16 | L2 |
| S-0956 | XLSX export with formulas and CSV | E08 | Must | 10 | L2 |
| S-0957 | Snapshot and versioning of approved reports | E08 | Must | 8 | L2 |
| S-0958 | Batch generation for a month as ZIP | E08 | Should | 8 | L2 |
| S-0959 | Tax engine (tax case determination, reasons, overrides) | E09 | Must | 24 | L3 |
| S-0960 | Country packs DE, BA and generic EU | E09 | Must | 14 | L3 |
| S-0961 | Invoice drafting, including from service reports and times | E09 | Must | 20 | L3 |
| S-0962 | Gapless numbering sequences | E09 | Must | 8 | L3 |
| S-0963 | Finalization, immutability, cancellation and credit notes | E09 | Must | 12 | L3 |
| S-0964 | Invoice documents (PDF and DOCX) in the document language | E09 | Must | 14 | L3 |
| S-0965 | External fiscal system reference | E09 | Must | 4 | L3 |
| S-0966 | Payments and open items (basic) | E09 | Must | 8 | L3 |
| S-0967 | Down payment and final invoices | E09 | Should | 12 | L3 |
| S-0968 | ZUGFeRD/Factur-X generation (EN 16931 profile) | E10 | Must | 16 | L3 |
| S-0969 | XRechnung (CII and UBL) with inline mandatory field hints | E10 | Must | 12 | L3 |
| S-0970 | Validation against EN 16931 and XRechnung rules | E10 | Must | 14 | L3 |
| S-0971 | Send invoices by email with per-language templates | E10 | Must | 8 | L3 |
| S-0972 | Archive originals with hash and retention | E10 | Must | 6 | L3 |
| S-0973 | Licensing domain and Founding Customer plan | E11 | Must | 12 | L3 |
| S-0974 | Ed25519 license signing and offline validation | E11 | Must | 12 | L3 |
| S-0975 | Direct sale flow (order, invoice, pending payment, activation email) | E11 | Must | 16 | L3 |
| S-0976 | License screen and activation (online code, offline file) | E11 | Must | 14 | L3 |
| S-0977 | Check-in, soft enforcement and edition gating | E11 | Must | 16 | L3 |
| S-0978 | License portal (basic) | E11 | Must | 14 | L3 |
| S-0979 | Renewal reminders and "Add users" order request | E11 | Should | 6 | L3 |
| S-0980 | PROJEKTRA production instance provisioned and protected | E12 | Must | 12 | Hardening |
| S-0981 | BA configuration and documents for PROJEKTRA | E12 | Must | 10 | L3 |
| S-0982 | Fiscalization decision record | E12 | Must | 3 | L3 |
| S-0983 | Customer zero acceptance run | E12 | Must | 8 | Hardening |
| S-0984 | First customer managed dedicated instance and license | E12 | Must | 8 | Hardening |
| S-0985 | No-index headers, meta tags, robots.txt and no-referrer | E13 | Must | 8 | L1 |
| S-0986 | Minimal fingerprint and leak-free errors | E13 | Must | 6 | L1 |
| S-0987 | Secure shared links | E13 | Must | 8 | L1 |
| S-0988 | CI crawl test and external doctor check | E13 | Must | 10 | L1 |
| S-0989 | Installation guide (AMP and Docker) in bs, en, de | E14 | Must | 8 | Hardening |
| S-0990 | Admin quick start in bs, en, de | E14 | Must | 8 | Hardening |
| S-0991 | User quick starts for employee, project lead and controller | E14 | Must | 12 | Hardening |
| S-0992 | Tour engine, help panel and "Getting started" tutorials | E14 | Must | 16 | L3 |
| S-0993 | Release notes, changelog, "What's new" and demo script | E14 | Must | 6 | Hardening |
| S-0994 | Moderated usability test of core tasks | E14 | Must | 8 | Hardening |
| S-0995 | Quotes with line items, versions and costing view | E15 | Should | 18 | L3 |
| S-0996 | Quote export as XLSX costing sheet with formulas | E15 | Should | 8 | L3 |
| S-0997 | Quote documents and one-click project from an accepted quote | E15 | Should | 14 | L3 |
| S-0998 | Absence calendar (basic) | E15 | Should | 20 | L3 |
| S-0999 | Expenses (mileage, hotel, per diems) | E15 | Should | 14 | L3 |

## Totals

**Per priority**

| Priority | Stories | Hours |
|---|---|---|
| Must | 85 | 1,091 |
| Should | 14 | 154 |
| Total | 99 | 1,245 |

**Per sprint**

| Sprint | Must (h) | Should (h) | Total (h) |
|---|---|---|---|
| L1 Foundation (18-21 Nov) | 342 | 0 | 342 |
| L2 Core (22-25 Nov) | 242 | 42 | 284 |
| L3 Money and onboarding (26-28 Nov) | 375 | 112 | 487 |
| Hardening (29-30 Nov) | 132 | 0 | 132 |
| Total | 1,091 | 154 | 1,245 |

**Per epic (Must / Should)**

| Epic | Must (h) | Should (h) |
|---|---|---|
| E01 Foundation | 198 | 10 |
| E02 i18n and RTL | 90 | 0 |
| E03 Installation and updates | 110 | 0 |
| E04 Onboarding | 90 | 20 |
| E05 Clients, projects, contracts | 64 | 6 |
| E06 Time tracking | 70 | 10 |
| E07 Rates | 30 | 8 |
| E08 Service reports | 64 | 8 |
| E09 Invoicing and tax | 104 | 12 |
| E10 E-invoicing | 56 | 0 |
| E11 Licensing | 84 | 6 |
| E12 PROJEKTRA instance and delivery | 41 | 0 |
| E13 Invisible by default | 32 | 0 |
| E14 Docs, tutorials, usability | 58 | 0 |
| E15 Should have (quotes, absences, expenses) | 0 | 74 |
| Total | 1,091 | 154 |

## Capacity check

### Capacity model

- Build capacity is counted in **calendar days** of the day plan, because the plan already runs through the weekends: L1 = 4, L2 = 4, L3 = 3, Hardening = 2, total **13 days**.
- Capacity is expressed in the same unit as the estimates: **productive ideal hours** (hours of focused delivery work, after meetings, coordination, status reports and interruptions).
- Scenario (a): **1 lead engineer with automated implementation support**, **14 ideal hours per day**. This is an optimistic upper bound: it assumes the automated support multiplies the output of one person and that 14 productive hours per day can be sustained for 13 consecutive days without loss of quality.
- Scenario (b): **a team of 3 developers at 6 ideal hours per day each = 18 ideal hours per day**. This assumes all three are productive from day one (no ramp-up), which is optimistic for a greenfield codebase.

### Calculation

| Sprint | Days | (a) capacity 14 h/day | (b) capacity 3 x 6 = 18 h/day | Must demand | Must + Should demand |
|---|---|---|---|---|---|
| L1 Foundation | 4 | 4 x 14 = 56 | 4 x 18 = 72 | 342 | 342 |
| L2 Core | 4 | 4 x 14 = 56 | 4 x 18 = 72 | 242 | 284 |
| L3 Money and onboarding | 3 | 3 x 14 = 42 | 3 x 18 = 54 | 375 | 487 |
| Hardening | 2 | 2 x 14 = 28 | 2 x 18 = 36 | 132 | 132 |
| **Total** | **13** | **182** | **234** | **1,091** | **1,245** |

Demand vs capacity (Must only):

| Sprint | Ratio (a) | Ratio (b) | Gap (a) | Gap (b) |
|---|---|---|---|---|
| L1 | 6.1 x | 4.8 x | 286 h | 270 h |
| L2 | 4.3 x | 3.4 x | 186 h | 170 h |
| L3 | 8.9 x | 6.9 x | 333 h | 321 h |
| Hardening | 4.7 x | 3.7 x | 104 h | 96 h |
| **Total** | **6.0 x** | **4.7 x** | **909 h** | **857 h** |

Sensitivity: even if every estimate were 40 % too high, Must demand would be about 655 h, still 3.6 times capacity (a) and 2.8 times capacity (b). The conclusion does not depend on estimate precision.

Dependency check: the longest dependency chain (tenancy, permissions, strings, clients, projects, contracts, work packages, time entries, rates, approvals, service report engine, invoice drafting, numbering, finalization, e-invoice generation and validation, direct sale flow, PROJEKTRA instance, customer zero run, release candidate, delivery) is about **330 to 390 ideal hours of strictly sequential work** (330 if the tax engine and licensing domain are built in parallel against agreed contracts). That chain alone exceeds the total capacity of both scenarios, so adding people within the 13 days cannot close the gap either.

### Verdict

**The Must scope does not fit, in either scenario.**

- Scenario (a), 1 lead engineer with automated implementation support: 182 h capacity against 1,091 h Must demand (6.0 x). It does not fit.
- Scenario (b), 3 developers: 234 h capacity against 1,091 h Must demand (4.7 x). It does not fit.
- The Should items (154 h) are therefore out of 0.9 and move to 0.10 (18 Dec). This is the planning assumption, not a decision to revisit on 26 Nov.
- Cutting scope alone cannot close the gap without touching what must not be touched (security, tenant isolation, tests, legal correctness of invoices). Even after every cut proposed below (212 h, plus 60 h of phased items), Must demand stays at about 819 to 879 h, 3.5 to 4.8 times capacity.

To deliver the post-cut Must scope (879 h) in 13 days would take about 68 ideal hours per day, that is about 11 developers at 6 h/day. That is not realistic: there is no time to onboard them, and the sequential chain above limits parallel work.

### Proposed cuts and simplifications

Untouchable and therefore not in this list: authentication with enforced 2FA (TOTP), tenant isolation and its cross-tenant tests, permissions and field-level permissions, audit log, web security baseline, invisible-by-default rules and CI crawl test, the CI quality gates and the database matrix, the independent security review, the tax engine, country packs DE and BA, gapless numbering, immutability and cancellation, e-invoice validation, signed releases and signed licenses.

Ranked by hours saved, with risk:

| Rank | Cut or simplification | Stories | Hours saved | Risk | Why acceptable |
|---|---|---|---|---|---|
| 1 | Onboarding simplified for a PROJEKTRA-led onboarding: defer workspace wizard, role welcome flow and tour engine (quick-start docs cover the tutorials); permission matrix reduced to role templates plus assignment; project wizard reduced to blank plus one template | S-0930, S-0934, S-0992, S-0932, S-0935 | 58 | Medium | First customer is onboarded by PROJEKTRA in a live session (C-03); settings screens exist; enforced 2FA stays in S-0904 |
| 2 | Defer AMP web installer and the Docker one-command script to 0.9.x; keep the AMP-profile CI job so the product still provably runs without Redis or Docker | S-0925, S-0926, part of S-0929 and S-0989 | 38 | Low | Not delivery critical (C-03); AMP compatibility remains tested in CI |
| 3 | Replace the one-click update UI by the CLI update with backup and automatic rollback (S-0927), operated by PROJEKTRA for managed instances; UI ships in 0.10 before any self-hosted customer | S-0928 | 26 | Low for managed delivery, medium for self-hosted promise | Same safety steps (backup, signature check, rollback) in the CLI |
| 4 | Licensing for a managed first customer: defer license portal, online activation code and daily check-in; keep signed licenses, offline license file, license screen, soft enforcement and edition gating | S-0978, parts of S-0976 and S-0977 | 26 | Low | PROJEKTRA installs the license on its own managed instance |
| 5 | RTL and preview languages depth: defer ar visual regression suite, RTL PDF shaping and machine-prefilled fr/es/ar/fa packs; keep logical CSS and its lint rule | parts of S-0920, S-0921, S-0954 | 14 | Low | ar and fa are preview in 0.9 and complete in 1.0 anyway |
| 6 | Defer admin language customization UI to 0.10 (data model for overrides prepared) | S-0918 | 14 | Low | bs, en and de are complete; no customer need on day one |
| 7 | Defer passkeys; TOTP 2FA stays enforced for admin and finance roles | S-0905 | 12 | Low to medium | Security level kept by enforced TOTP; confirm with security reviewer |
| 8 | Service report DOCX: built-in templates in bs, en, de only; template upload and assignment UI to 0.10 | S-0955 | 8 | Low | DOCX output itself stays |
| 9 | Invoice documents PDF only in 0.9 (Word in 0.10); archived legal original is the PDF anyway | S-0964 | 4 | Low | Legal content unchanged |
| 10 | Generic EU country pack deferred; DE and BA kept, EU reverse charge logic stays in the tax engine | S-0960 | 4 | Low | Both 0.9 sellers are in DE or BA |
| 11 | XRechnung in CII syntax only; UBL in 0.10 | S-0969 | 4 | Low | XRechnung CII is a valid XRechnung syntax |
| 12 | Contracts: defer role quotas | S-0941 | 4 | Low | Budget alerts stay |
| | **Total cuts** | | **212** | | Must demand after cuts: **879 h** |

Phased items (high business impact, product owner decision needed, not a quality cut):

| Rank | Phase-out | Stories | Hours moved | Risk |
|---|---|---|---|---|
| P1 | E-invoicing (ZUGFeRD, XRechnung, validation, sending, archive) in a 0.9.x release; normal PDF invoices with full tax logic in 0.9.0 | E10 (after cut 11) | 52 | Medium: first customer cannot issue e-invoices to its own clients on day one (German B2B issuing obligation starts in 2027/2028, receiving is planned for 0.10) |
| P2 | Direct sale flow automation: invoice and license created by the licensing manager by hand instead of automatically from the order | S-0975 | 8 | Low |
| | **Total phased** | | **60** | Must demand for 0.9.0: **819 h** |

### Recommendation

1. **Decide on 17 Nov (Phase 0), before L1 starts**, because every day of delay burns capacity on scope that will be cut. The date is fixed, quality and security are fixed, so the content of what is "delivered" on 1 Dec has to change.
2. Recommended option: **sell and deliver the Founding Customer license on 1 Dec, deliver function in stages.** On 1 Dec: contract and Founding Customer plan signed, signed license issued, managed dedicated instance provisioned with the "0.9.0 Early Access" build containing whatever has passed the full Definition of Done by the go/no-go. Function follows in 0.9.x releases in this order: foundation and security, then time tracking, rates and service reports (the most important features for daily use), then invoicing and tax, then e-invoicing and the remaining onboarding.
3. Expected staging with the post-cut Must scope of 879 h (foundation and infrastructure about 306 h, core work about 214 h, money, licensing, onboarding, docs and hardening about 359 h), counting 7-day weeks until 30 Nov and 5-day weeks afterwards, with no work 24 Dec to 1 Jan:
   - Scenario (b), 18 h/day: foundation complete about 4 Dec; time tracking and service reports about 22 Dec; full post-cut 0.9 scope about end of January 2027.
   - Scenario (a), 14 h/day: foundation complete about 11 Dec; time tracking and service reports about mid January 2027; full post-cut 0.9 scope about mid to late February 2027, which collides with 1.0 (1 Mar 2027).
4. Consequence for the roadmap: 0.10 (18 Dec) and 1.0 (1 Mar 2027) must be replanned after this decision. The first customer's contract should state the staged delivery explicitly.
5. If the date must carry real function for the customer on 1 Dec, the only way is the time tracking and service report slice on top of the foundation (about 520 h after cuts), which needs either about 40 ideal hours per day from 18 Nov (roughly 7 developers productive from day one, not realistic) or a later date (about 22 Dec in scenario (b)).

## Decision of 17.11.2026

The product owner accepted the recommended option (confirmed decision C-06 in `docs/decisions/assumptions.md`):

- **Early access plus staged delivery.** On 1 Dec the Founding Customer license is sold and signed, a signed license is issued, and the managed dedicated instance is delivered with the "0.9.0 Early Access" build. That build contains only stories that passed the full Definition of Done by the go/no-go on 30 Nov. Nothing unfinished is switched on or reported as done.
- **All 12 cuts above (212 h) are applied.** Must demand for 0.9 is now **879 h**. The cut parts move to the target releases named in the cuts table.
- **Should items (154 h) are out of 0.9.** S-0914, S-0933, S-0938, S-0943, S-0949, S-0952, S-0958, S-0967, S-0979 and E15 are planned in the replanned 0.10.
- **Unchanged:** every untouchable item listed above the cuts table, the Definition of Done, tests first for money, tax and time, and the independent security review.
- **Still open:** phased items P1 (e-invoicing) and P2 (manual sale flow). Until decided, both stay in 0.9.3 and 0.9.4.
- The first customer's contract states the staged delivery and the release order below.

### Staged releases

Order is fixed; dates are targets for scenario (b) and are recalibrated on 21 Nov from the actual L1 velocity. Each release passes the full Definition of Done, the database matrix and a security review of its own scope before it reaches the customer.

| Release | Content | Target (b) | Target (a) |
|---|---|---|---|
| 0.9.0 Early Access | Signed license, managed instance, everything that passed the Definition of Done by 30 Nov (expected: large part of the foundation, invisible-by-default rules, Docker production stack, signed release pipeline) | 01.12.2026 | 01.12.2026 |
| 0.9.1 Foundation complete | Rest of E01, E02 and E03 after cuts, E13; CLI update with backup and rollback | about 04.12.2026 | about 11.12.2026 |
| 0.9.2 Core work | E05 clients, projects, contracts; E06 time tracking; E07 rates; E08 service reports | about 22.12.2026 | about mid January 2027 |
| 0.9.3 Money | E09 invoicing and tax; E11 licensing after cuts; E12 PROJEKTRA instance with BA configuration | January 2027 | February 2027 |
| 0.9.4 Complete | E10 e-invoicing (unless P1 moves it), E04 onboarding after cuts, E14 docs and usability test | about end of January 2027 | about mid to late February 2027 |

Consequences:

- Sprints L1 to Hardening in this file now plan the 0.9.0 Early Access build. L1 starts with the stories on the critical path; feature freeze and go/no-go dates stay.
- 0.10 (planned 18.12.2026) and 1.0 (planned 01.03.2027) cannot keep their dates and content. They are replanned after the recalibration on 21 Nov, together with the decision on scenario (a) or (b).

## Critical path

Sequential chain (ideal hours in brackets), any slip moves delivery:

1. Phase 0: ADRs, data model, repository and dev server (A-01)
2. S-0902 tenancy (18) and S-0906 permissions (22)
3. S-0916 string system (18)
4. S-0939 clients (16), S-0940 projects (14), S-0941 contracts (16), S-0942 work breakdown structure (18)
5. S-0944 time entry domain (18), S-0950 rates (22), S-0947 approvals (16)
6. S-0953 service report engine (20)
7. S-0959 tax engine (24) and S-0960 country packs (14), can start in parallel from L2 against agreed contracts
8. S-0961 invoice drafting (20), S-0962 numbering (8), S-0963 finalization and cancellation (12)
9. S-0968 ZUGFeRD (16), S-0970 validation (14)
10. S-0973 licensing domain (12), S-0974 signing (12), S-0975 direct sale flow (16)
11. S-0980 PROJEKTRA instance (12), S-0983 customer zero run (8)
12. S-0929 release candidate and fresh-server tests (14), go/no-go 30 Nov 16:00
13. S-0984 first customer instance and license (8), 1 Dec

Parallel infrastructure path that must join before step 11: S-0923 Docker production stack, S-0924 signed release pipeline, S-0927 CLI update with rollback, S-0985 to S-0988 invisible by default.

## Top 5 risks

| # | Risk | Probability | Impact | Mitigation |
|---|---|---|---|---|
| 1 | Capacity gap of 4.7 to 6 times on the Must scope; continuing as planned leads to unfinished, untested work on 1 Dec | Certain | Critical | Scope and delivery decision on 17 Nov; staged delivery as recommended; no placeholder implementation reported as done |
| 2 | Legal wording and fiscalization not confirmed: BA place-of-supply notice (A-04) and FBiH fiscalization (A-05) depend on PROJEKTRA's accountant; a "fiscal adapter required" answer becomes a blocker | Medium | High | Ask the accountant now with a written question (S-0982); external fiscal reference (S-0965) as fallback; no real invoice before sign-off |
| 3 | E-invoice validation in pure PHP (EN 16931 and XRechnung Schematron) is harder than estimated; library choice not yet made | Medium | High | Technical spike and ADR in L1; use the KoSIT validator as a service on the managed instance; phase E10 to 0.9.x if needed (P1) |
| 4 | Quality under time pressure: three-database matrix, RTL checks, 85 % domain coverage and an independent security review compressed into 2 hardening days; late high findings force a no-go; sustained 14-hour days increase defects in money and tax logic | High | High | Tests first for money, tax and time; security review of auth, isolation and licensing already at the end of L1 and L2, not only in Hardening; sustainable working hours |
| 5 | Infrastructure readiness: dev server (A-01), PROJEKTRA production VPS (A-07), signing keys and backups not ready in time; customer-zero rule (3 working days of internal use) cannot be met for 0.9 | Medium | High | Provision production and staging hosts in L1 in parallel; record the customer-zero deviation with explicit product owner approval in the go/no-go record |

Further open items: official logo SVGs (A-02), first customer contract terms for a staged delivery (A-03).
