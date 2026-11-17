# UX principles and review checklist

Status: Phase 0 baseline, 16.11.2026. Owner: ux-ui-designer.

Usability is a feature with acceptance criteria, not decoration. Every screen is reviewed against this checklist before its story counts as done. The reviewer records the result in the pull request (pass, fail with reason, or not applicable with reason). Any "fail" blocks the merge.

Benchmarks: speed and polish like Linear, Stripe Dashboard and Notion; depth like Projektron; simplicity like Freedcamp.

---

## 1. Principles

| ID | Principle | What it means in practice |
|---|---|---|
| P1 | Simple by default, powerful on demand | A new user sees a calm, focused screen. Advanced options appear through progressive disclosure (expanders, "More options", settings), never all at once. |
| P2 | Zero training | An employee books time and a project lead creates a service report without reading documentation. |
| P3 | Fewest interactions for the core flows | Book time in at most 3 interactions; service report in 1 click via preset; invoice from service report in 1 click plus confirmation; project from accepted quote in 1 click. |
| P4 | Undo instead of confirm | Reversible actions run immediately with an undo toast; confirmation dialogs only for irreversible or financially binding actions (posting or cancelling an invoice, sending to a customer). |
| P5 | Instant feedback | Optimistic UI; every action acknowledges within 100 ms; spinners only for operations over 1 s, with progress for operations over 3 s. |
| P6 | Keyboard first | Command palette, shortcuts for all frequent actions, timesheet fully keyboard-operable, shortcut overview on `?`. |
| P7 | Clear language | Short labels, verbs first on buttons, no jargon, helpful empty states, inline validation with actionable messages, contextual help. Tone of voice per `docs/ux/brand.md`. |
| P8 | Consistency | One design system (tokens, components, patterns) documented in Storybook or Histoire; no one-off components without design system entry. |
| P9 | Light and dark, two densities | Light (default) and dark mode; comfortable and compact density. |
| P10 | Every device | Responsive for desktop, tablet and phone; installable PWA for time tracking, approvals and tickets. |
| P11 | Accessible in both directions | WCAG 2.2 AA in LTR and RTL. |
| P12 | Fast | Interactions feel instant; page transitions p95 under 300 ms on the performance dataset. |

## 2. Measurable targets

| ID | Target | How it is measured | When |
|---|---|---|---|
| T1 | Time to first value under 10 minutes (company set up, first project, first time entry) | Timed scripted run from fresh install, plus observed in usability tests | Every milestone |
| T2 | Task success rate at least 90 % for core tasks | Moderated usability test with 5 participants, documented in `docs/ux/usability/<release>.md` | End of every milestone |
| T3 | System Usability Scale 85 or higher | SUS questionnaire after the usability test | Before release 1.0 (tracked from 0.9) |
| T4 | Book time in at most 3 interactions | Playwright test counts clicks and key presses from the timesheet | Every build |
| T5 | Service report in 1 click via preset | Playwright test | Every build |
| T6 | Invoice from service report in 1 click plus confirmation | Playwright test | Every build |
| T7 | Project from accepted quote in 1 click | Playwright test | From 0.10 (quotes) |
| T8 | Page transitions p95 under 300 ms with 100,000 time entries and 50,000 tickets per tenant on the Docker reference setup | Performance test suite | From 1.0 (A-12); smoke measurement in 0.9 |
| T9 | First-load JavaScript within the defined budget | Bundle size check in CI fails when the budget is exceeded | Every build |
| T10 | Zero WCAG 2.2 AA violations detected automatically | axe-core in Playwright on every main screen in `bs` (LTR) and `ar` (RTL), light and dark | Every build |
| T11 | No visual regressions in LTR and RTL | Playwright screenshot comparison per main screen in `bs` and `ar` | Every build |

## 3. Screen review checklist

Copy this list into the pull request for every new or changed screen.

### 3.1 Flow and simplicity
- [ ] Flows and wireframes were delivered and agreed before implementation.
- [ ] The screen has one clear primary action; at most one primary button per region.
- [ ] Advanced options are behind progressive disclosure; the default view is calm.
- [ ] The core flow on this screen meets its interaction target (T4 to T7 where applicable).
- [ ] Sensible defaults are prefilled (date, project, rate, language, template).
- [ ] Reversible actions use undo, not a confirmation dialog; irreversible or financially binding actions explain consequences in the confirmation.

### 3.2 Feedback and states
- [ ] Optimistic update with rollback and a clear message on failure.
- [ ] Loading state (skeleton, not a blank screen), empty state (explains what to do next, with a primary action), error state (what happened, how to fix), partial and offline states (PWA screens).
- [ ] Inline validation on blur and on submit, with actionable messages next to the field; focus moves to the first error.
- [ ] Success feedback is quiet (toast or inline), not a modal.

### 3.3 Keyboard
- [ ] Every action reachable and operable by keyboard; logical focus order (also in RTL).
- [ ] Visible focus ring (`--pt-color-focus`, 2 px, offset 2 px) never hidden.
- [ ] Frequent actions have shortcuts, registered in the command palette and listed on `?`.
- [ ] No keyboard traps; dialogs trap focus correctly and return it on close; `Esc` closes overlays.

### 3.4 Language and i18n
- [ ] No hard-coded text; all strings are identifiers present in `en` and `bs`.
- [ ] Labels are short, verbs first on buttons, no jargon; brand appears only as `{brand}`.
- [ ] Plural forms correct (Bosnian one, few, other; Arabic six forms).
- [ ] Layout survives pseudo-localization (`xx`, +40 % length) without truncating meaning.
- [ ] Dates, numbers, currencies and durations formatted by locale; tabular numbers for amounts, hours and dates.

### 3.5 RTL
- [ ] Screen checked in `ar`: layout mirrors (navigation, sidebars, breadcrumbs, tables, forms, boards, charts where meaningful).
- [ ] Only logical CSS properties (lint green).
- [ ] Directional icons mirror, non-directional icons do not (per `docs/ux/brand.md`).
- [ ] Mixed-direction content isolated (`<bdi>`, `dir="auto"`) for ticket keys, amounts, emails, URLs and Latin names.

### 3.6 Accessibility (WCAG 2.2 AA)
- [ ] axe-core reports no violations (light and dark, LTR and RTL).
- [ ] Text contrast at least 4.5:1 (3:1 for large text); controls and graphical objects at least 3:1; only role tokens from the brand system are used.
- [ ] Information is never conveyed by color alone (status has icon and text).
- [ ] Target size at least 24 x 24 px (2.5.8); focus not obscured by sticky headers (2.4.11).
- [ ] Drag-and-drop has a non-drag alternative (2.5.7), for example move via menu or keyboard.
- [ ] Forms have visible labels, accessible names, error identification, and do not ask again for information already entered (3.3.7 redundant entry).
- [ ] Authentication does not rely on cognitive tests; paste and password managers allowed (3.3.8).
- [ ] Screen reader check of the main flow (one screen reader per milestone, rotating).
- [ ] Content reflows at 320 CSS px width and 200 % zoom without loss.
- [ ] Respects `prefers-reduced-motion` and `prefers-contrast`.

### 3.7 Consistency and visual quality
- [ ] Uses design system components and tokens only; new patterns are added to the design system with documentation.
- [ ] Light and dark mode both reviewed; comfortable and compact density both reviewed.
- [ ] Beam gradient is not used behind dense data; Signal only for progress and highlights.
- [ ] Spacing on the 4 px scale; radii per token (buttons 16, inputs 12, cards 22).

### 3.8 Responsive and PWA
- [ ] Works on phone (360 px), tablet (768 px) and desktop (1280 px and wider).
- [ ] Touch targets at least 40 x 40 px on touch devices.
- [ ] PWA screens (time tracking, approvals, tickets) usable offline where specified, with clear sync status.

### 3.9 Performance
- [ ] No new request waterfalls; data loaded with the page (Inertia props) or in one deferred request.
- [ ] Lists over 100 rows are paginated or virtualized.
- [ ] Bundle budget check green (T9); route-level code splitting for heavy components (editor, Gantt, charts).

### 3.10 Security and privacy in the UI
- [ ] Fields the user may not see are absent (not blank, not hidden by CSS).
- [ ] No sensitive data in URLs, page titles or toasts.
- [ ] External links use the `ExternalLink` component (`rel="noopener noreferrer"`).
- [ ] Before login, nothing shows product name, version or framework.

### 3.11 Help and onboarding
- [ ] Contextual help available where the task is not self-explanatory (tooltip, help panel, link to docs).
- [ ] New concepts are introduced once (first-run hint), dismissible, and never block work.
- [ ] "Getting started" checklist updated if the screen is part of a first-value path.

## 4. Usability test protocol (per milestone)

1. Recruit 5 participants matching the personas (employee, project lead, controller, admin; at least one non-native speaker of the test language). Use synthetic data only.
2. Core tasks for 0.9: set up the workspace, invite a colleague, create a client and a project from template, book time for a week, submit and approve the week, create a service report, create and send an invoice with e-invoice.
3. Measure per task: success (yes, with help, no), time, errors, interactions for the core flows; SUS after the session.
4. Record findings with severity (blocker, major, minor) in `docs/ux/usability/<release>.md`; blockers are fixed before release or the release scope is cut.
5. Success criteria: task success at least 90 % (T2); trend of SUS toward 85 (T3).
