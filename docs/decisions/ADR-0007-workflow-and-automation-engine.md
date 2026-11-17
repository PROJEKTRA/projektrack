# ADR-0007: Workflow and automation engine

## Status

Accepted, 16.11.2026

## Context

One engine must serve tickets, service desk, projects, work packages, quotes, invoices, absences, timesheets and roadmaps. No module builds its own automation. Requirements:

- **Workflows**: configurable state machines per entity type and project, statuses grouped into To do, In progress, Done; transitions with conditions, validators and post-functions; visual designer with simulation; versioning and safe migration of existing items; shipped templates (simple task, software development, Scrum, Kanban, IT incident, service request, problem, change with approval, content production, quote approval, invoice approval, absence approval).
- **Automation rules**: no-code builder with triggers (events, SLA thresholds, approvals, inbound email and webhooks, schedules aware of business calendars, manual buttons, time entry submitted, invoice overdue, budget threshold, absence approved), conditions (fields, PQL, roles, customer or contract, business hours, branches) and actions (set fields, transition, assign, create items, comment, email, notify, webhook, approvals, draft quotes or invoices, AI actions); delays and waits; smart values; scopes per project, customer, global; template library.
- **Safety**: loop detection, execution limits per rule and tenant, run log with inputs and outputs, test mode and dry run, enable/disable, ownership, audit trail, permission checks as rule owner or service actor.
- **Approvals**: single, multi-step and parallel, by email link or portal, delegation to deputies.
- **SLAs**: business-hour calendars, holidays, response and resolution goals per priority and contract, pause on "waiting for customer", warnings and breaches.
- Runs on AMP via database queue and cron; real-time execution with Redis workers.
- 0.9 ships without the engine, but its data (timesheet approvals) must fit the engine in 1.0 without breaking changes.
- Community gets workflows and basic automation; Business gets the full engine.

## Decision

1. **Data-driven, versioned state machines.** Workflow definitions, versions, statuses, transitions and transition rules are stored as data (see [data-model.md](../architecture/data-model.md), section 14). Statuses belong to the definition and are stable across versions; transitions belong to a version. Published versions are immutable. Every item records the version it runs on.

2. **Safe version migration.** Publishing a new version that removes statuses requires a status mapping; a resumable `workflow_migrations` job moves items in batches and logs each transition with the actor "system migration". Items keep working on the old version until migrated.

3. **Rules are registered code, configured by data.** Conditions, validators, post-functions, triggers, automation conditions and actions are PHP classes registered under stable keys through extension points (ADR-0002), each with a JSON Schema for its configuration and a translated label. Users compose them; users never write code. Modules contribute their own (for example `invoices.create_draft_invoice`, `ai.classify`).

4. **One runtime contract.** `WorkflowRuntime` exposes `availableTransitions(subject, actor)`, `transition(subject, transitionKey, actor, input)` and `simulate(definitionVersion, scenario)`. Issues store their status directly on `issues` for query speed; all other entity types use `workflow_item_states`. The runtime writes `workflow_transition_log` and publishes `IssueTransitioned` or `WorkflowTransitioned` through the outbox.

5. **Automation rule definition as a validated JSON tree**: `{ trigger, conditions, branches[], actions[] }`, stored in immutable `automation_rule_versions`. The builder UI edits the tree; the backend validates it against the registered schemas and the rule owner's permissions on save.

6. **Event-driven execution.** Triggers subscribe to outbox events (at-least-once, deduplicated by event ID per rule). Scheduled triggers are evaluated by the scheduler (one cron entry) using business calendars. Execution runs as a queued job: on AMP via the database queue processed by cron, with Redis workers when available. Manual triggers run synchronously up to a short time budget, then continue queued.

7. **Execution identity.** A rule runs as its owner or as a dedicated per-tenant service actor (`run_as`). Every action performs the normal policy and field permission checks for that identity; a rule can never do what its identity cannot. Changing a rule's owner or identity requires the rule management permission and is audited.

8. **Loop and runaway protection**:
   - Every event carries `causation_id` and `causation_depth`; actions executed by a rule emit events with depth plus one. Execution stops at depth 5 (configurable down, not up) and logs `AutomationLimitReached`.
   - A rule does not trigger on events caused by itself within the same causation chain unless explicitly allowed.
   - Counters per rule and per tenant (`automation_counters`) enforce limits per minute and per day; exceeding them pauses the rule and notifies the owner.
   - Each run has a maximum number of actions and a maximum wall time.

9. **Waits and delays** persist in `automation_waits` with `resume_at` (computed with business calendars) or `resume_on_event`. The scheduler resumes due waits. State is serialized data, never closures, so waits survive deployments.

10. **Dry run and test mode.** Dry run evaluates triggers and conditions against real data and records the actions that would run, without side effects (actions implement a `plan()` method in addition to `execute()`). Every rule can be tested against a chosen item before enabling. The run log stores inputs and outputs, redacted by the run identity's field permissions.

11. **Smart values** use a small, sandboxed template language (variable paths, filters, formatting by locale), parsed and evaluated by our own interpreter: no access to PHP, no loops beyond a bounded `each`, output escaped for the target channel (HTML email, plain text, JSON). Values respect field permissions of the run identity.

12. **Approvals** are a shared service (`approval_requests`, steps, assignees, decisions) used by workflow validators ("requires approval"), automation actions ("start approval") and modules directly. Policies: single, sequential multi-step, parallel (all or any). Delegation resolves deputies from the absence module at assignment time and records `delegated_from_id`. Decisions by email link use single-use, expiring shared links.

13. **SLA timers** are computed, not ticked. A timer stores `started_at`, accumulated business minutes and the next `due_at` computed from the business calendar; pause and resume recompute `due_at`. The scheduler scans `(status, due_at)` for warnings and breaches each minute and emits `SlaWarningReached` and `SlaBreached`. This works on AMP without long-running processes.

14. **AI actions** (classify, summarize, extract, draft, translate) are regular actions that call the AI gateway (ADR-0009). They respect AI policies, run in dry run like every action, and any customer-facing or financial output goes through an approval before it is sent or saved. Auto-run of low-risk AI actions requires `allow_ai_auto_run` on the rule, granted by an admin.

15. **0.9 compatibility.** In 0.9, timesheet approval is a built-in fixed flow (submit, approve or reject per project lead) recorded in `timesheet_approvals`. In 1.0 a shipped workflow definition "Timesheet approval" reproduces this flow; `timesheet_approvals.approval_request_id` links new decisions to the engine. Existing 0.9 records stay valid without migration. The same pattern applies to absence approvals in 0.10.

16. **Edition split.** Community: workflows, designer, event triggers, simple conditions and actions, manual triggers, run log. Business (`automation_pro`): scheduled triggers, branching, delays and waits, approvals in rules, SLA automation, template library, service actors, AI actions.

## Alternatives considered

| Alternative | Why not chosen |
|---|---|
| Framework-level workflow component with definitions in code or YAML | Good runtime, but definitions are static configuration; per-tenant, per-project editable and versioned workflows with item migration need a data-driven model. Its ideas (guards, marking) are reused. |
| External BPMN engine | Powerful, but requires a separate Java or Go service, which breaks the AMP profile and adds operational weight; BPMN is too complex for the "easy" goal. |
| Durable workflow orchestration service | Excellent for long-running processes, but needs dedicated infrastructure and workers; not compatible with shared hosting. |
| Embedding a general-purpose automation tool | Separate runtime, separate permission model, data leaves the product; conflicts with security and white label. |
| Per-module bespoke automation | Duplicated logic, inconsistent safety; explicitly ruled out by the product requirements. |
| User-written scripts in rules | Maximum flexibility, but a code execution surface in a multi-tenant system; rejected for security. |

## Consequences

Positive:
- One consistent mental model for users across tickets, approvals, quotes, invoices and absences.
- Safe by construction: permissions per run identity, loop limits, dry run, full run log.
- Works on plain AMP; scales with Redis workers without design change.
- 0.9 data stays valid when the engine arrives.

Negative and risks:
- Engine latency on AMP is bounded by the cron interval (up to about a minute for queued rules); acceptable, documented, and improved by real workers.
- The registry of conditions and actions must be kept stable and versioned; renaming a key breaks stored rules, so keys are permanent and deprecations are mapped.
- Workflow migrations on large tenants (50,000 tickets) need batching and progress UI.
- The visual designer and simulation are significant frontend work in 1.0.

## Open questions

- Default and maximum values for per-tenant execution limits per edition (to be calibrated with the 1.0 performance tests).
- Whether PQL conditions are evaluated in the database (fast, needs indexes) or in memory against the triggering item (simpler); likely both depending on the condition.
- Retention of run logs per edition (default 90 days proposed).
- Whether quotes and invoices use workflows for internal approval only, or also expose workflow statuses to customers.
