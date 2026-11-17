# ADR-0005: Internationalization, string system and RTL

- **Status:** Accepted, 16.11.2026
- **Deciders:** Product owner, solution architecture, i18n and localization, UX
- **Related:** ADR-0001 (stack), ADR-0002 (module language files), ADR-0004 (legal notices), ADR-0006 (tenant overrides, white label)

## Context

Internationalization is part of the foundation of Release 0.9 (sprint L1), not an add-on. The model is Moodle's language system: string identifiers, language packs, admin language customization and community translation ([Moodle String API](https://moodledev.io/docs/apis/subsystems/string), [Moodle language customisation](https://docs.moodle.org/en/Language_customisation)).

Shipped locales:

| Locale | Language | Direction | 0.9 status | 1.0 status |
|---|---|---|---|---|
| `bs` | Bosnian, Latin script (assumption A-08), **default locale** | LTR | 100 % | 100 % |
| `en` | English, **technical source language** | LTR | 100 % | 100 % |
| `de` | German | LTR | 100 % | 100 % |
| `fr` | French | LTR | machine-prefilled, "preview" | 100 % |
| `es` | Spanish (neutral international) | LTR | machine-prefilled, "preview" | 100 % |
| `ar` | Arabic | RTL | machine-prefilled, "preview"; RTL layout working | 100 %, RTL exports |
| `fa` | Farsi / Persian | RTL | machine-prefilled, "preview"; RTL layout working | 100 %, RTL exports |

**Hebrew is out of scope:** no `he` (or legacy `iw`) locale is shipped, offered, listed or accepted by the language pack installer.

Regional formats (dates, numbers, currency, paper size, first day of week) are chosen separately from the UI language (for example Spanish UI with `en-US` formats).

## Decision

### 1. String identifiers

- Format: `<component>:<key>`, for example `core:save`, `timesheets:submit_week`, `invoices:reverse_charge_notice`.
- `component` is `core` or a module key (`snake_case`, matches `module.json` `key`). Third-party modules use their own module key, so identifiers never collide.
- `key` matches `^[a-z][a-z0-9_]*(\.[a-z0-9_]+)*$`, maximum 100 characters. Conventions: `_help` suffix for help texts, `_label`, `_placeholder`, `_error_<reason>`, `nav.` prefix for navigation, `email.` prefix for email templates, `enum.<enum>.<case>` for enum labels.
- **Never change the meaning of an existing key.** A changed meaning gets a new key; the old key is listed in `lang/deprecated.json` with its replacement and removed after one minor release. Changing only the wording of an `en` source string marks all translations "needs review".
- The brand is never in strings: `{brand}` is a reserved argument injected automatically from the tenant brand configuration (white label, ADR-0006).
- No user-facing text in code: validation messages, emails, notifications, PDF/DOCX/XLSX exports, enum labels, error messages and API error `message` fields all use identifiers. The API returns the identifier as `message_key` plus the resolved `message` in the request language.

### 2. Message syntax: ICU MessageFormat

- All values are [ICU MessageFormat](https://unicode-org.github.io/icu/userguide/format_parse/messages/) (version 1 syntax): named arguments, `plural`, `select` (including gender), `selectordinal`, `number` and `date` skeletons.
- Example `timesheets:hours_booked`:
  - en: `{count, plural, one {# hour booked} other {# hours booked}}`
  - bs: `{count, plural, one {# sat evidentiran} few {# sata evidentirana} other {# sati evidentirano}}`
  - ar: `{count, plural, zero {لا ساعات} one {ساعة واحدة} two {ساعتان} few {# ساعات} many {# ساعة} other {# ساعة}}`
- **Plural categories follow [CLDR plural rules](https://www.unicode.org/cldr/charts/latest/supplemental/language_plural_rules.html):** `bs` has one, few, other (1 sat, 2 sata, 5 sati; 21 sat, 22 sata, 25 sati; fractional values use the `v`/`f` operands); `ar` has zero, one, two, few, many, other; `fa` has one, other; `de`, `en` one, other; `fr` one, many, other; `es` one, many, other.
- A CI check parses every message and fails if a plural message lacks a category required by the locale, or uses an argument not declared in the `en` source.

### 3. One translation layer for backend and frontend

- **Source files:** `lang/<locale>/<component>.json` for core and `modules/<Key>/lang/<locale>/<component>.json` per module: a flat JSON object `{ "key": "ICU message" }`. JSON is chosen because Weblate handles it natively with ICU checks and it is trivially diffable.
- **Backend (PHP):** formatting with ext-intl [`MessageFormatter`](https://www.php.net/manual/en/class.messageformatter.php) (ext-intl is a hard installation requirement, ADR-0001). A `Strings` service resolves the identifier through the override and fallback chain (section 5), formats with the effective locale and caches parsed patterns. Laravel's `__()` and validation messages are bridged to this service, so framework texts also come from identifiers.
- **Compiled cache:** on deploy, on language pack install and on every override change, resolved strings are compiled per locale (and per tenant if the tenant has overrides) into PHP arrays under `bootstrap/cache/strings/` (OPcache-friendly) and into versioned JSON bundles `strings.<locale>.<hash>.json` for the browser.
- **Frontend (Vue):** a small `useStrings()` composable with `t(id, args)` using FormatJS [`intl-messageformat`](https://formatjs.github.io/docs/intl-messageformat/) 12.x with the browser's `Intl.PluralRules`. Bundles are loaded per page on demand (core bundle plus active module bundles), cached by hash, and invalidated through the Inertia shared prop `strings_version`. Missing identifiers are reported to the backend (rate-limited) and fall back as on the server.
- **Parity:** a CI test renders a corpus of messages (all plural categories, numbers, dates, selects for each shipped locale) in PHP and in Node and fails on any difference. ICU versions differ between PHP builds and browsers; the parity test documents the minimum ICU version (verify at scaffold which ICU the target PHP 8.4 builds ship) and the installer warns if the server ICU is older.
- **Dates, numbers, currencies:** always through `Intl`/ICU with the user's regional format setting: PHP `IntlDateFormatter` and `NumberFormatter`, browser `Intl.DateTimeFormat` and `Intl.NumberFormat`. Amounts, hours and dates use tabular numerals in the UI.
- **Native digits:** per user setting for `ar` and `fa`: Western digits (`latn`), Arabic-Indic (`arab`) or Extended Arabic-Indic (`arabext`), applied through the Unicode locale extension `-u-nu-<system>`. Financial documents to customers default to Western digits unless the document language and customer setting say otherwise.

### 4. Admin overrides (Moodle-style language customization)

- Overrides are stored in `string_overrides(scope, tenant_id null, locale, identifier, value, source_hash, updated_by, updated_at)`. `scope` is `instance` or `tenant`.
- Admin UI: search by identifier or text, filter by module, side-by-side view of `en` source, shipped translation, installed language pack value and local override; highlight missing and outdated. Edit validates ICU syntax and argument names before saving.
- **Survive updates:** `source_hash` stores the hash of the `en` source text at the time of the override; after an update, overrides whose source hash changed are flagged "source changed, review".
- **Translation mode (optional):** admins can switch on inline editing; strings render with an invisible marker and an edit affordance; implemented after the core string screen (1.0).
- Legal notice strings (ADR-0004) need an extra permission and are audit-logged.

### 5. Resolution order and fallback chain

For identifier `id` and requested locale chain `[L1, L2, ...]`:

1. Tenant override for `L1`
2. Instance override for `L1`
3. Installed language pack for `L1` (may be newer than the shipped files)
4. Shipped strings for `L1`
5. Repeat 1 to 4 for the next locale in the chain

Default chain: user language, then tenant language, then `bs`, then `en`. The chain is configurable per instance. Regional variants fall back to the base language first (`es-US` -> `es`). If nothing is found the user sees the `en` source (always present by CI rule), never a raw key; the miss is logged once per identifier, locale and day.

Documents (quotes, invoices, service reports, emails to customers) use the **customer's document language** chain, independent of the creator's UI language.

### 6. Language packs and Weblate

- A language pack is a signed ZIP containing `lang/<locale>/*.json` for core and modules, a manifest (`locale`, `version`, `product_version_range`, completeness per component, checksum, signature).
- Install and update from Admin > Languages or CLI: `projektrack lang:install fr`, `projektrack lang:update`. Packs update independently of software releases. The installer refuses `he` and `iw`.
- Community translation via self-hosted [Weblate](https://weblate.org/) with Git sync of the JSON files, ICU MessageFormat checks enabled, glossary per language (`docs/i18n/`). Export and import in XLIFF and PO for agencies.
- Completeness per locale and component is shown in percent in the admin UI and on the docs site.
- Machine-prefilled strings carry a `needs_review` state in Weblate and a "preview" label on the locale in the language picker until reviewed.

### 7. RTL: logical CSS only

- `<html lang dir>` set server-side from the effective locale; content blocks with their own language (wiki pages, customer documents, user content) get their own `lang` and `dir`.
- **Logical properties only:** Tailwind 4 logical utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`, `text-start`, `text-end`, `border-s`, `border-e`, `rounded-s`, `rounded-e`, `float-start`, `float-end`) and CSS logical properties (`margin-inline-start`, `inset-inline-end`, `border-start-start-radius`).
- **Lint rules (CI gate):**
  - Stylelint with a logical-properties rule (for example [stylelint-use-logical](https://github.com/csstools/stylelint-use-logical), **verify at scaffold** that it supports the pinned Stylelint major) rejecting `left`, `right`, `margin-left`, `padding-right`, `border-left`, `text-align: left/right` and similar in CSS and `<style>` blocks.
  - A custom ESLint rule (and a grep-based check for Blade and PHP templates) rejecting physical Tailwind classes: `ml-`, `mr-`, `pl-`, `pr-`, `left-`, `right-`, `text-left`, `text-right`, `border-l`, `border-r`, `rounded-l`, `rounded-r`, `float-left`, `float-right`, `space-x-` without `rtl:` handling, `scroll-ml`, `scroll-pr`.
  - Exceptions need an inline comment `i18n-physical-ok: <reason>` (for example canvas coordinates in the Gantt chart, which use a direction-aware helper instead).
- **Icons:** directional icons (arrows, chevrons, undo/redo, progress, "next") use the `Icon` component with `mirror` (adds `rtl:-scale-x-100`); non-directional icons (clock, search, play, checkmarks) never mirror. The icon registry marks each icon as directional or not.
- **Components:** Reka UI primitives read `dir` from a `ConfigProvider` at the app root, so menus, popovers, sliders and keyboard navigation (arrow keys) follow the direction. The timesheet grid maps ArrowLeft/ArrowRight to logical previous/next day according to `dir`.
- **Charts:** time axes mirror in RTL where meaningful (Gantt, timelines); value axes stay; legends follow reading order.

### 8. Bidirectional text isolation

- Mixed-direction content (ticket keys like `PTK-123`, amounts, email addresses, URLs, code, names in Latin script inside Arabic text) is isolated:
  - In Vue: a `<Bidi>` component rendering `<bdi>` (or `dir="auto"` for block content), used by default for user-generated values in tables, lists and chips.
  - In ICU messages: arguments are wrapped in Unicode isolates (FSI U+2068 ... PDI U+2069) by the formatter when the target locale is RTL, so translators do not have to handle it.
  - Inputs for free text use `dir="auto"`; inputs for structured data (IBAN, VAT ID, email, numbers) use `dir="ltr"` with logical alignment.
  - CSS `unicode-bidi: isolate` on amount and identifier cells.
- Algorithm reference: [Unicode Bidirectional Algorithm, UAX #9](https://www.unicode.org/reports/tr9/).

### 9. Fonts (self-hosted, no CDN)

| Use | Font | License | Coverage |
|---|---|---|---|
| Headings, display | Mona Sans | SIL OFL 1.1 | Latin incl. Bosnian diacritics (č ć đ š ž), verify Latin Extended-A at scaffold |
| UI and body | Golos Text | SIL OFL 1.1 | Latin, Cyrillic |
| Arabic | Noto Naskh Arabic | SIL OFL 1.1 | Arabic |
| Farsi | Vazirmatn | SIL OFL 1.1 | Arabic script with Persian forms |

- WOFF2 files are subsetted at build time per script (Latin, Latin Extended, Arabic) and served from the application with `unicode-range`, so LTR users never download Arabic glyphs.
- Font stacks per locale: `bs/en/de/fr/es`: Golos Text; `ar`: Noto Naskh Arabic, then Golos Text for Latin runs; `fa`: Vazirmatn, then Golos Text.
- PDF exports embed TTF versions of the same fonts through mPDF font configuration (with Arabic shaping); DOCX templates reference the same family names.
- Font sources and versions are pinned in ADR-0001 (Golos Text and Noto Naskh Arabic: verify at scaffold).

### 10. Calendars

- Storage is always Gregorian dates and UTC timestamps; work dates are local dates in the tenant time zone.
- Display options per user: Gregorian (default), **Solar Hijri (Jalali)** for `fa`, **Hijri (Umm al-Qura)** for `ar`.
  - PHP: `IntlDateFormatter` with locale `fa_IR@calendar=persian` or `ar_SA@calendar=islamic-umalqura`.
  - Browser: `Intl.DateTimeFormat` with `calendar: 'persian'` or `'islamic-umalqura'`.
  - Date pickers: Reka UI date components use [@internationalized/date](https://react-spectrum.adobe.com/internationalized/date/), which provides `PersianCalendar` and `IslamicUmalquraCalendar`; values are converted to Gregorian `CalendarDate` before submit.
- Financial documents show the Gregorian date; an alternate calendar date may be added as a second line per template.
- First day of week, weekend days and holiday calendars come from the regional settings, not from the language.

### 11. RTL exports

- **PDF (mPDF):** `dir="rtl"` on the document or blocks, Arabic and Persian shaping via OTL-enabled fonts, `<bdi>` support for isolated runs, mirrored table column order. Tested by visual comparison.
- **DOCX (PhpWord):** paragraph style `bidi`, table `bidiVisual`, right alignment via logical template styles; RTL templates per language.
- **XLSX (PhpSpreadsheet):** `setRightToLeft(true)` on sheets for RTL document languages; formulas unchanged.
- **CSV:** UTF-8 with BOM for Excel compatibility; no direction metadata possible, documented.

### 12. Pseudo-locales

- `xx`: pseudo-localization of `en` (accented characters, +40 % length, bracket markers `[!! ... !!]`) to find hard-coded strings, truncation and concatenation.
- `xx-rtl`: same text with RTL direction and mirrored layout, to test RTL without reading Arabic.
- Both are available in development, CI and demo instances, hidden in production unless enabled by an instance admin.

### 13. CI quality gates

The build fails if:

1. A user-facing string is hard-coded (ESLint rule for Vue templates and TS, Blade lint, PHPStan rule for string literals passed to response, mail, notification, validation, export and enum label APIs).
2. An identifier used in code is missing in `en` or `bs`.
3. An identifier exists in `bs` or another locale but not in `en` (orphan).
4. A message fails ICU parsing, uses undeclared arguments, or lacks a required CLDR plural category.
5. Physical CSS directions or physical Tailwind classes appear without an approved exception.
6. The PHP/JS formatting parity test fails.
7. A `he` or `iw` locale file appears anywhere.
8. Playwright visual regression for main screens in `bs` (LTR) and `ar` (RTL) shows unapproved differences; pseudo-locale smoke test shows untranslated text.
9. Plural tests per shipped language fail (for example `bs`: 1, 2, 5, 11, 21, 22, 25, 1.5; `ar`: 0, 1, 2, 3, 11, 100).

## Alternatives considered

- **Laravel's native `__()` with PHP array files and `trans_choice`:** no ICU MessageFormat, weak plural support for `ar` and `bs` (pipe syntax), no shared syntax with the frontend. Kept only as a bridge. Rejected as the primary layer.
- **gettext (PO files) with msgid as source text:** source-text keys break when English wording changes and do not fit Moodle-style overrides by identifier. Rejected; PO stays an exchange format.
- **vue-i18n:** solid, but its message syntax is not ICU MessageFormat, so backend and frontend would differ. Rejected in favor of FormatJS `intl-messageformat` with the same ICU messages as PHP.
- **MessageFormat 2 (Unicode MF2):** promising, but tooling (PHP intl, Weblate checks) is not yet ready for production in both runtimes. Re-evaluate for 2.x.
- **Separate RTL stylesheet (`rtlcss` flipping):** doubles CSS, misses dynamic styles, encourages physical properties. Rejected in favor of logical properties only.
- **Google Fonts or font CDN:** violates the no-third-party-request and invisibility rules. Rejected.

## Consequences

**Positive**
- One string system for UI, emails, exports and API messages, overridable per instance and tenant, translatable by the community.
- RTL works from 0.9 because layout is logical from the first component.
- CLDR-correct plurals in Bosnian and Arabic.

**Negative and risks**
- ext-intl becomes a hard requirement on shared hosting (widely available, but must be checked by the installer).
- ICU version differences between server and browser may produce small formatting differences; mitigated by the parity test and the documented minimum ICU version.
- Logical-only CSS requires discipline from all contributors; lint rules carry the burden.
- Machine-prefilled `fr`, `es`, `ar`, `fa` in 0.9 must be clearly labeled "preview" to avoid shipping misleading legal or financial texts; legal notices in these languages are not used on invoices until reviewed.

## Open questions

1. Product owner: confirm the default fallback chain (user, tenant, `bs`, `en`) also for customer documents, or `en` before `bs` for foreign customers (proposal: customer document language, then `en`, then `bs`).
2. Product owner: whether `ar` and `fa` invoices are needed in 0.9 at all (proposal: not before reviewed translations in 1.0).
3. Accountant: confirm that legal notices on PROJEKTRA's English invoices may be in English only, or must also appear in Bosnian.
4. i18n review: verify at scaffold Mona Sans coverage of Latin Extended-A and the ICU version of the target PHP builds.
