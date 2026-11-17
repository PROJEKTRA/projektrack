# Domain glossary

The glossary fixes product terms in English (string source language), Bosnian (default locale, Latin script) and German. UI strings, documents, help articles and translations use these terms. If a term changes, the string identifiers stay; only the translations change.

"String component hint" names the component where strings for the term usually live (identifiers follow `<component>:<key>`, for example `invoices:reverse_charge_notice`).

## Platform, users and licensing

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Tenant | Zakupac (organizacija) | Mandant | A company inside one instance with strictly separated data, own branding, numbering, tax settings, language, currency and time zone. | `core` |
| Instance | Instanca | Instanz | One installation of Projektrack (self-hosted, SaaS, dedicated or partner), holding one or more tenants. | `core` |
| Module | Modul | Modul | A functional unit that can be enabled or disabled per tenant. Disabling keeps its data. | `core` |
| Edition | Izdanje | Edition | A product package: Community, Business, Enterprise or Partner. | `licensing` |
| License | Licenca | Lizenz | The right to use an edition for a number of users, issued by PROJEKTRA as a signed token. | `licensing` |
| License key | Licencni ključ | Lizenzschlüssel | Signed token (Ed25519) with edition, user counts, expiry, instance binding and add-ons. Validated offline. | `licensing` |
| Activation code | Aktivacijski kod | Aktivierungscode | Short readable code (for example `PTK-7H4Q-M2XD-9KLA`) that an admin enters to download and apply the license. | `licensing` |
| License file | Licencna datoteka | Lizenzdatei | File containing the signed license for offline (air-gapped) installations. | `licensing` |
| License portal | Licencni portal | Lizenzportal | Portal where license customers see subscriptions, invoices, license files and activations. | `licensing` |
| Subscription | Pretplata | Abonnement | Plan, edition, user counts, add-ons, term, renewal date and price of a license customer. | `licensing` |
| Grace period | Period odgode | Kulanzfrist | Time during which a license works despite missing payment or exceeded user count (14 or 30 days). | `licensing` |
| Founding Customer plan | Plan za osnivačke klijente | Gründungskunden-Tarif | Business edition plan for the first customers from 01.12.2026 with price locked for 24 months. | `licensing` |
| Merchant of record | Posrednik u prodaji (merchant of record) | Merchant of Record (Händler im eigenen Namen) | A reseller that sells licenses to end customers, handles payment and taxes, and is invoiced by PROJEKTRA. | `licensing` |
| Full user | Puni korisnik | Vollbenutzer | Billed user with access to all licensed modules. | `licensing` |
| Light user | Ograničeni korisnik | Light-Benutzer | Lower-priced billed user limited to time, expenses, own tasks and approvals. | `licensing` |
| Portal user | Korisnik portala | Portalbenutzer | Free user at a customer who works only in the customer portal or through signed links. | `portal` |
| Freelancer (external user) | Vanjski saradnik | Freiberufler (externer Mitarbeiter) | External contributor with a reduced interface, usually a light user with contractor flag. | `core` |
| Role | Uloga | Rolle | A named set of permissions assigned to a user globally or per project. | `core` |
| Role template | Šablon uloge | Rollenvorlage | Ready-made role with a plain-language description that admins copy and adapt. | `core` |
| Permission | Ovlaštenje | Berechtigung | Right to perform an action. Deny by default. | `core` |
| Field-level permission | Ovlaštenje na nivou polja | Feldberechtigung | Permission to see or edit a sensitive field such as rates, costs, margins or salaries. | `core` |
| Audit log | Revizijski zapisnik | Prüfprotokoll | Immutable, hash-chained record of logins, permission changes, financial records, exports and settings. | `audit` |
| Shared link | Dijeljeni link | Freigabelink | Unguessable, expiring, revocable link for sign-off, roadmaps or downloads. | `core` |
| White label | Bijela etiketa (white label) | White Label (Eigenmarke) | Presenting the product under a tenant's or partner's own name, logo, colors and domain. | `branding` |
| Custom domain | Vlastita domena | Eigene Domain | A tenant's own domain name for its instance or portal, with automatic TLS. | `branding` |
| Country pack | Paket zemlje | Länderpaket | Configuration bundle per country: tax rates, mandatory invoice fields, legal notices, numbering, holidays, retention. | `tax` |
| Language pack | Jezički paket | Sprachpaket | Installable set of translated strings for one locale. | `core` |
| String identifier | Identifikator teksta | Text-ID | Key of the form `component:key` used instead of text in code. | `core` |
| String override | Prilagođeni tekst | Textanpassung | Admin change of a translated string per instance or tenant that survives updates. | `core` |

## Customers, projects and contracts

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Customer (client) | Klijent | Kunde | A company or person the tenant works for and invoices. | `crm` |
| Contact | Kontakt osoba | Ansprechpartner | A person at a customer. Can be invited as a portal user. | `crm` |
| Document language | Jezik dokumenata | Dokumentsprache | Language in which documents for a customer are generated, independent of the user's UI language. | `crm` |
| Project | Projekat | Projekt | A piece of work for a customer with lead, period, billing model, budget and health indicator. | `projects` |
| Project lead | Voditelj projekta | Projektleiter | Person responsible for planning, approvals and reporting of a project. | `projects` |
| Health indicator | Pokazatelj stanja projekta | Projektampel | Traffic light showing project status from configurable rules. | `projects` |
| Contract | Ugovor | Vertrag | Agreement under a project defining type, budget, term, rates and quotas. Several per project. | `contracts` |
| Statement of work (SOW) | Opis obima radova (SOW) | Leistungsbeschreibung (SOW) | The contract document that defines scope, budget and rates of work. Modeled as a contract. | `contracts` |
| Framework agreement | Okvirni ugovor | Rahmenvertrag | Contract type with call-offs drawn against agreed conditions. | `contracts` |
| Call-off | Pojedinačna narudžba (poziv) | Abruf | A single order placed under a framework agreement. | `contracts` |
| Time and material | Po utrošenom vremenu i materijalu | Nach Aufwand | Billing model where actual hours and costs are invoiced. | `contracts` |
| Fixed price | Fiksna cijena | Festpreis | Billing model with an agreed total independent of hours spent. | `contracts` |
| Hour package | Paket sati | Stundenkontingent | Prepaid number of hours consumed by bookings. | `contracts` |
| Retainer | Mjesečni paušal | Monatspauschale | Recurring monthly fee for agreed services or availability. | `contracts` |
| Role quota | Kvota po ulozi | Rollenkontingent | Hours per role and period allowed under a contract (for example 40 h per month). | `contracts` |
| Budget | Budžet | Budget | Planned hours and/or money for a project, contract or work package. | `projects` |
| Budget alert | Upozorenje o budžetu | Budgetwarnung | Notification when consumption reaches a threshold (default 70 %, 90 %, 100 %). | `contracts` |
| Work breakdown structure (WBS) | Struktura raščlanjivanja posla (WBS) | Projektstrukturplan (PSP) | Hierarchical tree of work packages with numbering such as 1, 1.1, 1.1.2. | `projects` |
| Work package | Radni paket | Arbeitspaket | Unit of planned work with owner, hours, cost, dates, status, billable flag, contract and service category. | `projects` |
| Service category | Kategorija usluge | Leistungsart | Configurable type of work such as consulting, development or support, used for grouping and rates. | `projects` |
| Milestone | Prekretnica | Meilenstein | A dated checkpoint in a project plan with no duration. | `projects` |
| Project template | Šablon projekta | Projektvorlage | Reusable project set-up with WBS, roles, workflows, automation, views and wiki pages. | `templates` |

## Time, absences and expenses

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Time entry | Unos vremena | Zeitbuchung | A record of minutes worked on a work package or ticket on a date, with activity text. | `timesheets` |
| Timesheet | Evidencija radnog vremena | Stundenzettel | Weekly grid of time entries per work package or ticket and day. | `timesheets` |
| Timer | Mjerač vremena | Timer | Start/stop clock that creates a time entry. | `timesheets` |
| Recurring booking | Ponavljajući unos | Wiederkehrende Buchung | Proposed time entry repeated on a schedule, confirmed by the user before saving. | `timesheets` |
| Rounding rule | Pravilo zaokruživanja | Rundungsregel | Rule that rounds durations (for example up to 15 minutes) per tenant, customer or contract. | `timesheets` |
| Submission | Predaja | Einreichung | The employee's act of handing in a week or month for approval. | `timesheets` |
| Approval | Odobrenje | Freigabe | Confirmation by an authorized person that locks the approved items. | `approvals` |
| Correction entry | Korektivni unos | Korrekturbuchung | Traceable entry that corrects approved time without changing the original. | `timesheets` |
| Period lock | Zaključavanje perioda | Periodensperre | Controller's monthly close that blocks new or changed bookings in that month. | `timesheets` |
| Absence | Odsustvo | Abwesenheit | Time a person is not available: vacation, sick leave, training, travel, closure. | `absences` |
| Absence calendar | Kalendar odsustava | Abwesenheitskalender | Team calendar of absences and public holidays that feeds capacity. | `absences` |
| Leave entitlement | Pravo na godišnji odmor | Urlaubsanspruch | Days or hours of leave a person may take per year, with carry-over rules. | `absences` |
| Deputy | Zamjenik | Stellvertretung | Person who takes over approvals, tickets and queues during an absence. | `absences` |
| Working time model | Model radnog vremena | Arbeitszeitmodell | Target working hours per day and week used for target vs. actual. | `timesheets` |
| Overtime balance | Saldo prekovremenih sati | Überstundensaldo | Difference between actual and target hours over time. | `timesheets` |
| Expense | Trošak | Spesen | Re-billable cost such as mileage, hotel or per diem, linked to a project. | `expenses` |
| Utilization | Iskorištenost | Auslastung | Booked hours compared to target hours, split into billable and non-billable. | `controlling` |

## Rates, reports and quotes

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Hourly rate | Cijena sata | Stundensatz | Price or cost of one hour of work. | `rates` |
| Rate hierarchy | Hijerarhija cijena sata | Stundensatzhierarchie | Ordered rule set that picks the most specific rate: entry, work package, contract, project, customer, employee, role, tenant default. | `rates` |
| Sales rate | Prodajna cijena sata | Verrechnungssatz | External hourly rate charged to the customer. | `rates` |
| Cost rate | Troškovna cijena sata | Kostensatz | Internal hourly cost of an employee or role, used for margin. | `rates` |
| Price list | Cjenovnik | Preisliste | Set of rates and prices with a validity period. | `rates` |
| Surcharge | Doplata | Zuschlag | Extra charge for weekend, holiday, night or on-call work, as percent or amount. | `rates` |
| Contribution margin | Doprinos pokriću | Deckungsbeitrag | Revenue minus direct costs for a line, work package or project. | `controlling` |
| Service report | Izvještaj o izvršenim uslugama | Leistungsnachweis | Document listing approved time for a period with totals, used for customer proof and invoicing. | `reports` |
| Report preset | Unaprijed postavljeni izvještaj | Berichtsvorlage | Saved configuration (scope, period, columns, grouping) that generates a service report in one click. | `reports` |
| Snapshot | Snimak stanja | Momentaufnahme | Frozen, hashed version of an approved report; changes create a new version. | `reports` |
| Customer sign-off | Potvrda klijenta | Kundenabnahme | Customer approval of a report via secure link, with name, time and IP logged. | `reports` |
| Quote | Ponuda | Angebot | Offer to a customer with versioned line items, validity date and status. | `quotes` |
| Optional item | Opcionalna stavka | Optionale Position | Quote line the customer may add; not part of the total by default. | `quotes` |
| Costing sheet | Kalkulacija | Kalkulationsblatt | XLSX export of a quote with formulas for cost, sales and margin. | `quotes` |
| Online acceptance | Online prihvatanje | Online-Annahme | Customer accepts a quote or order through a secure link. | `quotes` |

## Invoicing and tax

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Invoice | Faktura (račun) | Rechnung | Finalized, numbered request for payment. Immutable after finalization. | `invoices` |
| Finalize | Zaključiti fakturu | Festschreiben | Turning a draft into an immutable, numbered invoice. | `invoices` |
| Down payment invoice | Avansna faktura | Abschlagsrechnung | Invoice for a partial payment before or during the work. | `invoices` |
| Final invoice | Konačna faktura | Schlussrechnung | Invoice for the total that deducts earlier down payments. | `invoices` |
| Recurring invoice | Ponavljajuća faktura | Wiederkehrende Rechnung | Invoice generated on a schedule for retainers, maintenance or subscriptions. | `invoices` |
| Cancellation invoice | Storno faktura | Stornorechnung | Document that reverses a finalized invoice in full. Invoices are never deleted. | `invoices` |
| Credit note | Knjižno odobrenje | Gutschrift (Rechnungskorrektur) | Document that reduces an amount owed by the customer. In the US: credit memo. | `invoices` |
| Numbering sequence | Brojčani niz | Nummernkreis | Gapless, configurable sequence per tenant and document type (for example `INV-2026-0001`). | `invoices` |
| Tax case | Porezni slučaj | Steuerfall | The tax treatment applied to an invoice or line, determined automatically with a reason. | `tax` |
| VAT | Porez na dodanu vrijednost | Umsatzsteuer | Value added tax charged on supplies of goods and services. | `tax` |
| PDV | PDV | PDV (Umsatzsteuer in Bosnien und Herzegowina) | Bosnia and Herzegovina VAT, standard rate 17 %. | `tax` |
| VAT ID | PDV broj (ID broj za PDV) | USt-IdNr. | Tax identification number for VAT purposes, shown on invoices where required. | `crm` |
| Reverse charge | Prenos porezne obaveze na primaoca | Steuerschuldnerschaft des Leistungsempfängers (Reverse Charge) | B2B case where the customer, not the seller, owes the VAT (EU VAT Directive Art. 196, §13b UStG). Invoice shows 0 % with a legal notice and both VAT IDs. | `tax` |
| Small business exemption | Oslobođenje za male poreske obveznike | Kleinunternehmerregelung | Seller below a turnover threshold charges no VAT (for example §19 UStG), with a notice. | `tax` |
| Tax-exempt supply | Promet oslobođen poreza | Steuerfreie Leistung | Supply legally exempt from VAT, with the matching notice. | `tax` |
| Place of supply | Mjesto prometa usluge | Leistungsort | Country where a service is taxed; drives the tax case. | `tax` |
| Fiscalization | Fiskalizacija | Fiskalisierung | Legal registration of invoices with a tax authority system where a country requires it. | `tax` |
| E-invoice | E-faktura | E-Rechnung | Invoice in a structured, machine-readable format that complies with EN 16931. | `einvoice` |
| EN 16931 | EN 16931 (evropska norma za e-fakture) | EN 16931 (europäische Norm für E-Rechnungen) | European standard for the semantic data model of an electronic invoice. | `einvoice` |
| ZUGFeRD / Factur-X | ZUGFeRD / Factur-X | ZUGFeRD / Factur-X | Hybrid e-invoice: PDF/A-3 with embedded XML (CII). Default profile EN 16931. | `einvoice` |
| XRechnung | XRechnung | XRechnung | German e-invoice standard for public-sector customers, in UBL or CII syntax. | `einvoice` |
| Leitweg-ID | Leitweg-ID (oznaka primaoca u javnom sektoru) | Leitweg-ID | Buyer reference that routes e-invoices to German public-sector recipients. Required for XRechnung. | `einvoice` |
| E-invoice validation | Validacija e-fakture | Validierung der E-Rechnung | Check against EN 16931 and XRechnung rules before sending, with a readable error list. | `einvoice` |
| Incoming invoice | Ulazna faktura | Eingangsrechnung | Supplier invoice received by upload or inbound mailbox, parsed and assigned to a project. | `einvoice` |
| Peppol | Peppol | Peppol | International network for exchanging e-invoices through access points. | `einvoice` |
| Payment terms | Uslovi plaćanja | Zahlungsbedingungen | Due date rules and discounts agreed with a customer. | `invoices` |
| Open item | Otvorena stavka | Offener Posten | Invoice amount not yet fully paid. | `payments` |
| Dunning | Opominjanje | Mahnwesen | Process of reminding customers about overdue invoices. | `dunning` |
| Dunning level | Nivo opomene | Mahnstufe | Step in the dunning process with its own template and fee. | `dunning` |
| Retention period | Rok čuvanja | Aufbewahrungsfrist | Legal time documents must be kept unchanged, set per country pack. | `tax` |
| GoBD | GoBD | GoBD | German principles for proper digital bookkeeping and archiving. | `tax` |
| DATEV export | DATEV izvoz | DATEV-Export | Booking batch export in the format used by German tax advisors. | `exports` |
| Sales tax | Porez na promet (SAD) | Sales Tax (US-Umsatzsteuer) | US tax per state, county and city, applied per line instead of VAT. | `tax` |

## Work management, service desk and planning

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| Issue | Zadatak | Vorgang | A unit of tracked work such as epic, story, task, bug or sub-task. | `tasks` |
| Ticket | Tiket | Ticket | An issue raised by a customer or by email, usually handled in the service desk. | `servicedesk` |
| Board | Ploča | Board | Kanban or Scrum view of issues in columns by status. | `tasks` |
| Backlog | Lista zadataka (backlog) | Backlog | Ranked list of work not yet planned into a sprint or release. | `tasks` |
| Sprint | Sprint | Sprint | Time-boxed iteration with a committed set of issues. | `tasks` |
| PQL | PQL | PQL | Projektrack Query Language for filtering items, with localized autocomplete. | `pql` |
| Workflow | Tok rada | Workflow | Configurable state machine with statuses, transitions, conditions, validators and post-functions. | `workflow` |
| Transition | Prelaz | Übergang | A permitted move from one workflow status to another. | `workflow` |
| Automation rule | Pravilo automatizacije | Automatisierungsregel | No-code rule of trigger, conditions and actions run by the shared engine. | `automation` |
| Trigger | Okidač | Auslöser | Event that starts an automation rule. | `automation` |
| Smart value | Pametna vrijednost | Smart Value (Platzhalter) | Placeholder such as `{{ticket.key}}` filled at run time in templates. | `automation` |
| Dry run | Probno izvršavanje | Probelauf (Testmodus) | Running a rule so it only logs what it would do. | `automation` |
| Customer portal | Portal za klijente | Kundenportal | White-labeled portal where portal users raise and follow requests and view documents. | `portal` |
| Request type | Vrsta zahtjeva | Anfragetyp | Portal form type with its own fields and workflow. | `servicedesk` |
| Queue | Red čekanja | Warteschlange | Filtered list of tickets that a team works through. | `servicedesk` |
| SLA | Ugovor o nivou usluge (SLA) | Service-Level-Vereinbarung (SLA) | Agreed response and resolution times per priority and contract, measured in business hours. | `sla` |
| Business-hour calendar | Kalendar radnih sati | Geschäftszeitenkalender | Working hours and holidays used to measure SLA time. | `sla` |
| Escalation | Eskalacija | Eskalation | Action taken when an SLA threshold is reached or breached. | `sla` |
| Canned response | Pripremljeni odgovor | Textbaustein | Reusable reply text for frequent questions. | `servicedesk` |
| Incident | Incident | Störung (Incident) | Unplanned interruption or reduced quality of a service. | `servicedesk` |
| Change | Promjena | Änderung (Change) | Controlled modification of a service, with risk assessment and approval. | `servicedesk` |
| CSAT | Ocjena zadovoljstva klijenta (CSAT) | Kundenzufriedenheit (CSAT) | Customer satisfaction rating collected after a ticket is resolved. | `servicedesk` |
| Gantt chart | Gantov dijagram | Gantt-Diagramm | Timeline of work packages, tasks and milestones with dependencies. | `gantt` |
| Dependency | Zavisnost | Abhängigkeit (Anordnungsbeziehung) | Link between two items (finish-to-start, start-to-start, finish-to-finish, start-to-finish) with lead or lag. | `gantt` |
| Baseline | Osnovni plan | Basisplan | Saved copy of the plan used to compare plan vs. actual vs. forecast. | `gantt` |
| Critical path | Kritični put | Kritischer Pfad | Chain of dependent items that determines the earliest project end. | `gantt` |
| Slack | Vremenska rezerva | Puffer | Time an item can move without delaying the project end. | `gantt` |
| Auto-scheduling | Automatsko planiranje | Automatische Terminplanung | Scheduling of items from dependencies, calendars and absences. | `gantt` |
| Load histogram | Histogram opterećenja | Auslastungshistogramm | Chart of planned load vs. capacity per person and period. | `planning` |
| Plan vs. actual | Plan i ostvarenje | Soll-Ist-Vergleich | Comparison of planned and booked hours, cost and revenue. | `controlling` |
| Earned value | Ostvarena vrijednost | Fertigstellungswert | Budgeted value of the work actually completed. | `controlling` |
| Estimate at completion (EAC) | Procjena troškova po završetku (EAC) | Prognostizierte Gesamtkosten (EAC) | Forecast of total cost when the work is finished. | `controlling` |
| Roadmap | Plan razvoja proizvoda | Roadmap | Time-based or Now / Next / Later view of goals, initiatives and releases. | `roadmap` |
| Release | Izdanje verzije | Release | A dated version with scope and release notes. | `roadmap` |
| Space | Prostor | Bereich | Container of wiki pages with its own permissions. | `wiki` |

## AI (optional product feature, off by default)

| Term (en) | Bosnian (bs) | German (de) | Definition (en) | String component hint |
|---|---|---|---|---|
| AI provider | Pružalac AI usluga | KI-Anbieter | External or self-hosted model service connected through the provider layer by a tenant admin. | `ai` |
| AI action | AI akcija | KI-Aktion | Automation step that classifies, summarizes, extracts, drafts or translates. | `ai` |
| Data control | Kontrola podataka | Datenkontrolle | Admin settings that define which data categories each module may send to an AI provider. | `ai` |
| Human approval | Odobrenje od strane čovjeka | Menschliche Freigabe | Required confirmation by a person before AI output reaches a customer or changes financial data. | `ai` |
| Cost cap | Ograničenje troškova | Kostenobergrenze | Usage limit per tenant, module or user for AI calls. | `ai` |
