# Projektrack module catalog

Status: Phase 0 baseline, 16.11.2026. Owner: solution architecture. Mechanism: ADR-0002.

The core (`core`) is always active. Every other entry below is a module with its own `module.json`, migrations, language files, permissions and tests. Community modules live in `/modules/<Key>`, commercial modules in `/ee/modules/<Key>`. Module keys are stable identifiers and never change; display names are string identifiers (`<key>:module_name`).

Editions: **C** = Community (AGPL-3.0), **B** = Business, **E** = Enterprise, **P** = Partner, **V** = vendor-only (runs on the PROJEKTRA instance, not sold). A higher edition always includes the lower ones.

"Requires" lists hard dependencies (foreign keys allowed, module cannot be enabled without them). "Optional" lists soft integrations used through events and contracts when the other module is enabled.

## 1. Catalog

| Key | Name | Edition | Requires | Optional | First release | Permissions |
|---|---|---|---|---|---|---|
| core | Core platform | C | (none) | (none) | 0.9 | `core.admin`, `core.users.manage`, `core.roles.manage`, `core.settings.manage`, `core.modules.manage`, `core.strings.customize`, `core.audit.view`, `core.audit.export`, `core.files.upload`, `core.api_tokens.manage`, `core.shared_links.manage`, `core.branding.manage`, `core.license.manage`, `core.data_requests.manage` |
| onboarding | Onboarding and setup | C | core | crm, projects | 0.9 | `onboarding.workspace.run`, `onboarding.sample_data.manage`, `onboarding.import.run` |
| crm | Customers and contacts | C | core | invoices, quotes | 0.9 | `crm.view`, `crm.manage`, `crm.contacts.manage`, `crm.tax_profile.manage`, `crm.price_lists.manage`, `crm.portal.invite`, `crm.export` |
| projects | Projects, contracts and work packages | C | core, crm | timesheets, gantt, work | 0.9 | `projects.view`, `projects.create`, `projects.manage`, `projects.members.manage`, `projects.contracts.manage`, `projects.wbs.manage`, `projects.budget.view`, `projects.templates.manage` |
| rates | Rates and price rules | C | core, crm, projects | timesheets, quotes | 0.9 | `rates.view_sales`, `rates.view_cost`, `rates.manage`, `rates.exchange_rates.manage`, `rates.job_roles.manage` |
| timesheets | Time tracking | C | core, projects, rates | work, servicedesk, absences | 0.9 | `timesheets.view`, `timesheets.book`, `timesheets.book_for_others`, `timesheets.approve`, `timesheets.view_rates`, `timesheets.set_rate`, `timesheets.lock_period`, `timesheets.correct`, `timesheets.recurring.manage`, `timesheets.working_time.manage` |
| service_reports | Service reports | C | core, projects, timesheets | invoices, signoff | 0.9 | `service_reports.view`, `service_reports.generate`, `service_reports.approve`, `service_reports.view_amounts`, `service_reports.templates.manage`, `service_reports.batch.run`, `service_reports.import` |
| invoices | Invoicing and payments | C | core, crm | timesheets, service_reports, quotes, expenses | 0.9 | `invoices.view`, `invoices.create`, `invoices.finalize`, `invoices.cancel`, `invoices.tax_override`, `tax.legal_notice.override`, `invoices.payments.record`, `invoices.numbering.manage`, `invoices.export` |
| taxpack_de | Country pack Germany | C | core, invoices | absences | 0.9 | `taxpack_de.manage` |
| taxpack_ba | Country pack Bosnia and Herzegovina | C | core, invoices | absences | 0.9 | `taxpack_ba.manage` |
| taxpack_eu | Country pack EU generic | C | core, invoices | (none) | 0.9 | `taxpack_eu.manage` |
| einvoicing | E-invoice creation and validation (ZUGFeRD/Factur-X, XRechnung) | C | core, invoices | (none) | 0.9 | `einvoicing.generate`, `einvoicing.validate`, `einvoicing.send`, `einvoicing.settings.manage` |
| licensing | Licensing and license portal | V | core, crm, invoices | quotes | 0.9 | `licensing.view`, `licensing.plans.manage`, `licensing.subscriptions.manage`, `licensing.licenses.issue`, `licensing.licenses.revoke`, `licensing.keys.manage`, `licensing.revenue.view` |
| quotes | Quotes and costing | C | core, crm, rates | projects, invoices | 0.10 | `quotes.view`, `quotes.create`, `quotes.send`, `quotes.view_costs`, `quotes.convert`, `quotes.templates.manage` |
| expenses | Expenses and travel | C | core, projects, timesheets | invoices | 0.10 | `expenses.view`, `expenses.submit`, `expenses.approve`, `expenses.types.manage` |
| absences | Absence calendar and out-of-office | C | core | timesheets, workflow, planning | 0.10 | `absences.view_team`, `absences.request`, `absences.approve`, `absences.view_reasons`, `absences.entitlements.manage`, `absences.holidays.manage`, `absences.deputies.manage` |
| einvoice_inbound | Incoming e-invoices | B | core, crm, invoices, einvoicing | projects | 0.10 | `einvoice_inbound.view`, `einvoice_inbound.receive`, `einvoice_inbound.assign`, `einvoice_inbound.mailbox.manage` |
| dunning | Dunning | B | core, invoices | automation | 0.10 | `dunning.view`, `dunning.run`, `dunning.levels.manage` |
| accounting_export | Accounting exports (CSV, IIF; DATEV from 1.3) | C (CSV), B (DATEV) | core, invoices | einvoice_inbound | 0.10 | `accounting_export.run`, `accounting_export.settings.manage` |
| signoff | Customer sign-off workflows | B | core, service_reports | workflow | 1.0 | `signoff.request`, `signoff.view_evidence`, `signoff.settings.manage` |
| workflow | Workflow engine and designer | C | core | work, servicedesk, absences, quotes, invoices | 1.0 | `workflow.view`, `workflow.design`, `workflow.publish`, `workflow.migrate`, `workflow.approvals.decide` |
| automation | Automation (basic: event triggers, simple actions) | C | core, workflow | ai | 1.0 | `automation.view`, `automation.manage`, `automation.run_manual`, `automation.logs.view` |
| automation_pro | Automation (scheduled rules, branching, delays, approvals, template library) | B | core, workflow, automation | ai_pro | 1.0 | `automation_pro.manage_advanced`, `automation_pro.service_actor.manage` |
| work | Tasks, boards, backlog, sprints, PQL | C | core, projects, workflow | timesheets, product | 1.0 | `work.view`, `work.create`, `work.edit`, `work.delete`, `work.transition`, `work.assign`, `work.comment`, `work.bulk_edit`, `work.boards.manage`, `work.sprints.manage`, `work.custom_fields.manage`, `work.issue_types.manage` |
| gantt | Gantt (basic) | C | core, projects | absences, work | 1.0 | `gantt.view`, `gantt.edit` |
| taxpack_us | Country pack United States (sales tax, US formats, holidays) | C | core, invoices | absences | 1.0 | `taxpack_us.manage`, `taxpack_us.nexus.manage` |
| ai | AI provider layer and basic assists (off by default) | C | core | timesheets, quotes, automation | 1.0 | `ai.settings.manage`, `ai.providers.manage`, `ai.policies.manage`, `ai.usage.view`, `ai.use`, `ai.approve_output` |
| ai_pro | AI actions in automation, proposal drafts, service desk, finance and controlling assists | B | core, ai | automation_pro, servicedesk, einvoice_inbound | 1.0 | `ai_pro.use_advanced`, `ai_pro.auto_run.manage` |
| imports | Imports (clients CSV in core; Freedcamp 1.0; Jira, Confluence, MS Project 1.3) | C | core, projects | work, wiki | 1.0 | `imports.run`, `imports.view_logs` |
| sso_enterprise | SAML and SCIM | E | core | (none) | 1.0 | `sso_enterprise.manage` |
| white_label | Full white label (remove "Powered by", custom login, sender domain) | E (B as add-on) | core | (none) | 1.0 | `white_label.manage` |
| servicedesk | Service desk and ITSM | B | core, crm, projects, work, workflow, automation_pro | timesheets, wiki, ai_pro | 1.1 | `servicedesk.view_queues`, `servicedesk.agent`, `servicedesk.reply_public`, `servicedesk.request_types.manage`, `servicedesk.sla.manage`, `servicedesk.portal.manage`, `servicedesk.reports.view` |
| payment_links | Online payment links for tenant invoices | B | core, invoices | (none) | 1.1 | `payment_links.manage`, `payment_links.providers.manage` |
| gantt_pro | Advanced Gantt (critical path, baselines, auto-scheduling, portfolio) | B | core, projects, gantt | absences | 1.2 | `gantt_pro.baselines.manage`, `gantt_pro.portfolio.view` |
| planning | Resource planning | B | core, projects | absences, timesheets | 1.2 | `planning.view`, `planning.manage` |
| product | Product roadmaps, ideas and prioritization | B | core, work | absences | 1.2 | `product.view`, `product.manage`, `product.ideas.manage`, `product.roadmaps.share` |
| controlling_pro | Advanced controlling, dashboards and report builder | B | core, projects, timesheets | invoices, einvoice_inbound | 1.2 | `controlling_pro.view_margin`, `controlling_pro.reports.manage`, `controlling_pro.dashboards.share` |
| multi_company | Multi-company console | E | core | (none) | 1.2 | `multi_company.view`, `multi_company.manage` |
| wiki | Knowledge base | C | core | projects, work, servicedesk | 1.3 | `wiki.view`, `wiki.edit`, `wiki.spaces.manage`, `wiki.templates.manage`, `wiki.import` |
| mcp | MCP server | B | core | timesheets, work, service_reports | 1.3 | `mcp.connect`, `mcp.clients.manage` |
| calendar_sync | Calendar sync (ICS in absences from 0.10; CalDAV and calendar connectors) | C (ICS, CalDAV), B (connectors) | core | absences | 1.3 | `calendar_sync.connect` |
| partner_console | Partner console | P | core, licensing | white_label | 2.0 | `partner_console.view`, `partner_console.manage` |
| marketplace | Public plugin SDK and marketplace | C | core | (none) | 2.0 | `marketplace.install`, `marketplace.review_capabilities` |
| peppol | Peppol transmission | B | core, einvoicing | einvoice_inbound | 2.0 | `peppol.manage` |
| fiscal_ba | Fiscalization Bosnia and Herzegovina | C | core, invoices, taxpack_ba | (none) | 2.0 | `fiscal_ba.manage` |

Notes:
- Field permissions (for example `field:time_entries.sales_amount`, `field:quote_lines.unit_cost_minor`) are declared by modules in `field_permissions` and appear in the permission matrix with risk hints. A declaration can name a module permission (for example `timesheets.view_rates`) that grants read access to the field, so admins grant one permission instead of many fields.
- The Light set is owned by the core, not by module manifests, because it decides what a customer pays: `timesheets.view`, `timesheets.book`, `timesheets.approve`, `expenses.view`, `expenses.submit`, `expenses.approve`, `absences.request`, `absences.approve`, `work.view`, `work.transition`, `work.comment`, `core.files.upload`. A user is Light while every permission their global and project roles grant is in this set, and Full otherwise (ADR-0003); a role's licensed user type is derived the same way.
- `role_defaults` and `project_role_defaults` suggest grants for the role templates. They are applied once, when a tenant first enables the module. A permission added by a later module version is granted to no role until an admin grants it (deny by default).
- Edition gating is checked at enable time and on every request (expired commercial license: read-only).

## 2. module.json schema

Every module ships `module.json` in its root. The registry validates it against the schema below on install and on every update; an invalid manifest blocks installation with a readable error.

Example:

```json
{
  "$schema": "https://schemas.projektrack.example/module.schema.json",
  "key": "timesheets",
  "name_string": "timesheets:module_name",
  "description_string": "timesheets:module_description",
  "version": "1.0.0",
  "core": "^0.9 || ^1.0",
  "edition": "community",
  "vendor": "projektra",
  "requires": ["core", "projects", "rates"],
  "optional": ["work", "servicedesk", "absences"],
  "conflicts": [],
  "permissions": [
    "timesheets.view",
    "timesheets.book",
    "timesheets.approve",
    "timesheets.view_rates"
  ],
  "field_permissions": [
    { "entity": "time_entries", "field": "sales_amount", "default": "hidden", "risk": "financial", "permission": "timesheets.view_rates" }
  ],
  "role_defaults": {
    "employee": ["timesheets.view", "timesheets.book"],
    "controller": ["timesheets.view", "timesheets.approve", "timesheets.view_rates"]
  },
  "project_role_defaults": {
    "project_lead": ["timesheets.approve"]
  },
  "navigation": [
    { "label_string": "timesheets:nav", "route": "timesheets.index", "icon": "clock", "permission": "timesheets.view" }
  ],
  "settings_schema": "settings.schema.json",
  "capabilities_required": ["db.read", "db.write", "files.write", "mail.send"],
  "events": {
    "publishes": ["TimeEntryBooked", "TimesheetSubmitted", "TimesheetApproved"],
    "consumes": ["AbsenceApproved", "RateRuleChanged"]
  },
  "contracts": {
    "provides": ["TimeEntryQuery"],
    "uses": ["RateResolver"]
  },
  "extension_points": {
    "provides": ["timesheets.row_actions"],
    "uses": ["core.dashboard_widgets", "automation.triggers"]
  },
  "ai": {
    "use_cases": ["timesheets.improve_description", "timesheets.booking_suggestions"],
    "data_categories": ["time_descriptions", "project_metadata"]
  },
  "feature_flags": ["timesheets.calendar_view"],
  "signature": "signature.json"
}
```

JSON Schema (draft 2020-12):

```json
{
  "$schema": "https://json-schema.org/draft/2020-12/schema",
  "$id": "https://schemas.projektrack.example/module.schema.json",
  "title": "Projektrack module manifest",
  "type": "object",
  "additionalProperties": false,
  "required": ["key", "name_string", "version", "core", "edition", "requires", "permissions"],
  "properties": {
    "$schema": { "type": "string" },
    "key": { "type": "string", "pattern": "^[a-z][a-z0-9_]{1,40}$" },
    "name_string": { "$ref": "#/$defs/stringId" },
    "description_string": { "$ref": "#/$defs/stringId" },
    "version": { "$ref": "#/$defs/semver" },
    "core": { "type": "string", "description": "Composer-style constraint on the core version." },
    "edition": { "enum": ["community", "business", "enterprise", "partner", "vendor"] },
    "vendor": { "type": "string", "pattern": "^[a-z][a-z0-9-]{1,40}$" },
    "requires": { "$ref": "#/$defs/moduleKeys" },
    "optional": { "$ref": "#/$defs/moduleKeys" },
    "conflicts": { "$ref": "#/$defs/moduleKeys" },
    "permissions": {
      "type": "array",
      "items": { "type": "string", "pattern": "^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$" },
      "uniqueItems": true
    },
    "field_permissions": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["entity", "field", "default"],
        "properties": {
          "entity": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
          "field": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
          "default": { "enum": ["hidden", "read", "write"] },
          "risk": { "enum": ["none", "personal", "financial", "security"] },
          "permission": { "type": "string", "description": "A permission of this module that grants read access to the field." }
        }
      }
    },
    "role_defaults": { "$ref": "#/$defs/roleGrants" },
    "project_role_defaults": { "$ref": "#/$defs/roleGrants" },
    "navigation": {
      "type": "array",
      "items": {
        "type": "object",
        "additionalProperties": false,
        "required": ["label_string", "route"],
        "properties": {
          "label_string": { "$ref": "#/$defs/stringId" },
          "route": { "type": "string" },
          "icon": { "type": "string" },
          "permission": { "type": "string" },
          "group": { "type": "string" },
          "order": { "type": "integer" }
        }
      }
    },
    "settings_schema": { "type": "string", "description": "Relative path to a JSON Schema for module settings." },
    "capabilities_required": {
      "type": "array",
      "uniqueItems": true,
      "items": {
        "enum": [
          "db.read", "db.write", "files.read", "files.write", "mail.send", "mail.receive",
          "http.outbound", "queue.dispatch", "schedule.register", "ui.inject",
          "api.routes", "webhooks.receive", "ai.invoke", "secrets.store"
        ]
      }
    },
    "events": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "publishes": { "$ref": "#/$defs/names" },
        "consumes": { "$ref": "#/$defs/names" }
      }
    },
    "contracts": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "provides": { "$ref": "#/$defs/names" },
        "uses": { "$ref": "#/$defs/names" }
      }
    },
    "extension_points": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "provides": { "$ref": "#/$defs/dottedNames" },
        "uses": { "$ref": "#/$defs/dottedNames" }
      }
    },
    "ai": {
      "type": "object",
      "additionalProperties": false,
      "properties": {
        "use_cases": { "$ref": "#/$defs/dottedNames" },
        "data_categories": {
          "type": "array",
          "items": { "type": "string", "pattern": "^[a-z][a-z0-9_]*$" },
          "uniqueItems": true
        }
      }
    },
    "feature_flags": { "$ref": "#/$defs/dottedNames" },
    "signature": { "type": "string", "description": "Relative path to the detached Ed25519 signature file over the module archive checksum list." }
  },
  "$defs": {
    "stringId": { "type": "string", "pattern": "^[a-z][a-z0-9_]*:[a-z0-9_.]+$" },
    "semver": { "type": "string", "pattern": "^(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(-[0-9A-Za-z.-]+)?$" },
    "moduleKeys": {
      "type": "array",
      "items": { "type": "string", "pattern": "^[a-z][a-z0-9_]{1,40}$" },
      "uniqueItems": true
    },
    "names": {
      "type": "array",
      "items": { "type": "string", "pattern": "^[A-Z][A-Za-z0-9]+$" },
      "uniqueItems": true
    },
    "dottedNames": {
      "type": "array",
      "items": { "type": "string", "pattern": "^[a-z][a-z0-9_]*(\\.[a-z][a-z0-9_]*)+$" },
      "uniqueItems": true
    },
    "roleGrants": {
      "type": "object",
      "description": "Role template key to the permissions of this module suggested for it.",
      "propertyNames": { "pattern": "^[a-z][a-z0-9_]{0,63}$" },
      "additionalProperties": { "$ref": "#/$defs/dottedNames" }
    }
  }
}
```

Validation rules beyond the schema (enforced by the registry):
- `requires` must form an acyclic graph; every required module must be installed and enabled first. Disabling a module that others require is refused with a plain-language list of dependents.
- Every permission must start with the module key (or `field:` for field permissions declared separately). The only exception is the reserved `tax.` namespace, owned by the tax engine shipped with the `invoices` module (for example `tax.legal_notice.override`, ADR-0004).
- Fields with risk `financial` or `security` must default to `hidden`. A field's `permission` and every permission in `role_defaults` and `project_role_defaults` must be declared in the module's own `permissions`.
- Every string identifier used in the manifest must exist in the module's `lang/en` and `lang/bs` files.
- A module with `edition` other than `community` loads only when the installed license covers it; otherwise it is installed but not enableable.
- Third-party modules (`vendor` not `projektra`) display their `capabilities_required` to the admin, who must approve them before enabling; the approval is stored in `tenant_modules.approved_capabilities` and audited.
