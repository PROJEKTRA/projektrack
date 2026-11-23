# Brand system

Status: Phase 0 baseline, 16.11.2026. Owner: ux-ui-designer. The default theme of Projektrack is the PROJEKTRA brand translated into a calm, high-end product UI. Everything below is implemented as design tokens (CSS custom properties plus a Tailwind theme), so white-label themes only swap tokens. The design system (Storybook or Histoire) shows every token and component in light, dark, LTR and RTL.

---

## 1. Name and wordmark

| Item | Rule |
|---|---|
| Product name | **Projektrack** in running text (capital P, rest lowercase). Never "ProjekTrack", "Projek Track" or "PT". |
| Wordmark | **PROJEKTRACK** in uppercase, set in the same style as the PROJEKTRA company wordmark. Used in the app header, login screen, documents and the product site. |
| Endorsement line | "Projektrack by PROJEKTRA". Used on the login screen footer, About screen, product site and documents' footer. Hidden when white label removes "Powered by". |
| Code identifier | `projektrack` (lowercase, no spaces) for packages, CLI, config keys and image names. |
| Brand in strings | Never hard-coded. Translatable strings use the `{brand}` placeholder, so white-label tenants see their own name in every language. A lint rule fails the build on the literal product name in `lang/` and UI templates. |
| Before login | The login page title is generic and configurable; the product name, version and framework are not exposed before login (invisibility rule). The wordmark on the login screen is an image without an `alt` text revealing the product; `alt` is the configured tenant name. |

## 2. Tagline

- Default: **"Plan it. Track it. Prove it."**
- Stored as string `core:brand_tagline`, configurable and translatable.
- Reference translations: bs "Planiraj. Prati. Dokaži." / de "Planen. Erfassen. Nachweisen."
- Meaning: planning, delivery and proof of performance (service reports, invoices) in one product, mirroring PROJEKTRA's promise "From problem to sustainable solution".

## 3. Brand configuration (white label)

All fields live in one brand configuration per instance, overridable per tenant (subject to edition: removing "Powered by" requires the white-label add-on or Enterprise).

| Field | Type | Default | Notes |
|---|---|---|---|
| `name` | string | Projektrack | Fills `{brand}` |
| `short_name` | string | Projektrack | PWA manifest, narrow headers |
| `wordmark_text` | string | PROJEKTRACK | Used if no wordmark SVG is uploaded |
| `tagline` | translatable string | Plan it. Track it. Prove it. | |
| `logo_mark_svg` | SVG (sanitized) | Placeholder until PROJEKTRA supplies assets | "P" mark |
| `logo_wordmark_svg_light`, `logo_wordmark_svg_dark` | SVG (sanitized) | Placeholder | Light and dark variants |
| `favicon` | SVG plus PNG 32/180 | Generated from mark | |
| `app_icon` | PNG 192/512, maskable | Mark on Beam gradient | PWA |
| `colors` | token overrides | Section 4 | Validated on save (section 4.6) |
| `font_display`, `font_body` | uploaded WOFF2 or bundled choice | Mona Sans, Golos Text | Self-hosted only |
| `login_title` | translatable string | Generic ("Sign in") | Must not contain version |
| `login_background` | gradient token or image | Beam | |
| `domain`, `custom_domains` | host names | | See infrastructure profiles for CT log note |
| `support_email`, `support_url`, `help_url` | strings | | |
| `email_sender_name`, `email_sender_address` | strings | | SPF, DKIM, DMARC guidance |
| `legal_entity` | structured | PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (licensor) | Used in About, license screens and license invoices |
| `imprint_url`, `privacy_url`, `terms_url` | strings | | |
| `powered_by_visible` | boolean | true | Edition-gated |
| `document_templates` | DOCX/HTML per language | Brand defaults | Quotes, invoices, service reports |

## 4. Color

### 4.1 Brand tokens (constants)

These are the raw PROJEKTRA brand colors. Components never use them directly; they use the role tokens in 4.2, which map to brand tokens per mode.

| Token | Value | Use |
|---|---|---|
| `--pt-ink` | `#000D0E` | Primary text and icons in light mode |
| `--pt-bg` | `#F5F7F7` | App background (light) |
| `--pt-surface` | `#FFFFFF` | Cards, panels, tables (light) |
| `--pt-line` | `#000D0E1A` (ink at 10 %) | Decorative borders and dividers |
| `--pt-blue` | `#005EFF` | Primary action, links, focus ring, selected state |
| `--pt-blue-light` | `#4D8DFF` | Hover tints, charts, decorative on light; links and focus in dark mode |
| `--pt-navy` | `#0E285C` | Headings on light, secondary buttons, light-mode sidebar |
| `--pt-indigo` | `#3358DD` | Gradient stop, charts |
| `--pt-cyan` | `#6AD7EC` | Accent, highlights, light beam detail, charts |
| `--pt-navy-deep` | `#050B1F` | Dark mode surfaces |
| `--pt-night` | `#000012` | Dark mode background, login and marketing hero |

### 4.2 Role tokens (light and dark)

Derived tokens (marked "derived") were added to complete the system; all were checked against WCAG 2.2.

| Role token | Light | Dark | Use |
|---|---|---|---|
| `--pt-color-bg` | `#F5F7F7` | `#000012` | Page background |
| `--pt-color-surface` | `#FFFFFF` | `#050B1F` | Cards, tables, panels |
| `--pt-color-surface-raised` (derived) | `#FFFFFF` | `#0B1530` | Popovers, menus, dialogs |
| `--pt-color-text` | `#000D0E` | `#E6ECF5` (derived) | Body text |
| `--pt-color-text-muted` (derived) | `#4A5A63` | `#9AA8BF` | Secondary text, captions |
| `--pt-color-heading` | `#0E285C` | `#E6ECF5` | Headings |
| `--pt-color-line` | `#000D0E1A` | `#E6ECF51F` | Decorative dividers |
| `--pt-color-line-strong` (derived) | `#7A878D` | `#6E7FA0` | Input borders, control outlines (non-text 3:1) |
| `--pt-color-primary` | `#005EFF` | `#005EFF` | Primary button fill |
| `--pt-color-on-primary` | `#FFFFFF` | `#FFFFFF` | Text on primary fill |
| `--pt-color-link` | `#005EFF` | `#4D8DFF` | Links, selected text |
| `--pt-color-focus` | `#005EFF` | `#4D8DFF` | Focus ring (2 px, 2 px offset) |
| `--pt-color-secondary` | `#0E285C` | `#E6ECF5` | Secondary button text and outline |
| `--pt-color-accent` | `#6AD7EC` | `#6AD7EC` | Highlights, never text on light |
| `--pt-color-sidebar` | `#0E285C` | `#050B1F` | Navigation sidebar |

### 4.3 Contrast of the base palette

Measured with the WCAG 2.x relative luminance formula. Text needs 4.5:1 (normal) or 3:1 (large, 24 px or 18.66 px bold); UI components and graphical objects need 3:1.

| Foreground | Background | Ratio | Result |
|---|---|---|---|
| Ink `#000D0E` | Surface `#FFFFFF` | 19.74:1 | AA and AAA text |
| Ink `#000D0E` | Bg `#F5F7F7` | 18.36:1 | AA and AAA text |
| Blue `#005EFF` | Surface `#FFFFFF` | 5.19:1 | AA text |
| Blue `#005EFF` | Bg `#F5F7F7` | 4.83:1 | AA text |
| White | Blue `#005EFF` (button) | 5.19:1 | AA text |
| Navy `#0E285C` | Bg `#F5F7F7` | 13.23:1 | AA and AAA text |
| Muted `#4A5A63` | Surface `#FFFFFF` / Bg `#F5F7F7` | 7.16:1 / 6.66:1 | AA text |
| Blue light `#4D8DFF` | Surface `#FFFFFF` | 3.20:1 | Non-text only (never body text on white) |
| Line strong `#7A878D` | Surface `#FFFFFF` / Bg `#F5F7F7` | 3.70:1 / 3.44:1 | Non-text 3:1 |
| Dark text `#E6ECF5` | Night `#000012` / Navy deep `#050B1F` / Raised `#0B1530` | 17.53:1 / 16.46:1 / 15.19:1 | AA and AAA text |
| Dark muted `#9AA8BF` | Night / Navy deep / Raised | 8.65:1 / 8.13:1 / 7.50:1 | AA text |
| Blue light `#4D8DFF` (dark link) | Night / Navy deep / Raised | 6.51:1 / 6.12:1 / 5.64:1 | AA text |
| Blue `#005EFF` (button fill) | Navy deep `#050B1F` | 3.77:1 | Non-text 3:1 (button boundary) |
| Line strong dark `#6E7FA0` | Night / Navy deep | 5.16:1 / 4.85:1 | Non-text 3:1 |
| Cyan `#6AD7EC` | Night / Navy deep | 12.44:1 / 11.68:1 | AA text in dark mode |
| Cyan `#6AD7EC` | Surface `#FFFFFF` | 1.67:1 | Decorative only on light |

### 4.4 Semantic colors (derived)

Hues were chosen to sit next to the cool PROJEKTRA blues without competing with the primary blue: a blue-leaning green for success, a warm amber for warning (the only warm hue, so it stands out), a slightly cool red for danger, and a cyan-derived teal for info (distinct from the primary blue, so "info" is never mistaken for a link). Each role has a foreground (text and icons on normal surfaces), a subtle background (banners, badges), and a solid fill with its on-color.

**Light mode**

| Token | Value | Against | Ratio | Result |
|---|---|---|---|---|
| `--pt-success` | `#0A7A4F` | Surface `#FFFFFF` | 5.37:1 | AA text |
| | | Bg `#F5F7F7` | 5.00:1 | AA text |
| | | Subtle `--pt-success-subtle` `#E6F4EE` | 4.74:1 | AA text |
| `--pt-success-solid` / on-color | `#0A7A4F` / `#FFFFFF` | | 5.37:1 | AA text |
| `--pt-warning` | `#9A5B00` | Surface `#FFFFFF` | 5.43:1 | AA text |
| | | Bg `#F5F7F7` | 5.05:1 | AA text |
| | | Subtle `--pt-warning-subtle` `#FDF1DC` | 4.86:1 | AA text |
| `--pt-warning-solid` / on-color | `#F2B544` / `#000D0E` | | 10.78:1 | AA and AAA text |
| `--pt-danger` | `#C42B3C` | Surface `#FFFFFF` | 5.59:1 | AA text |
| | | Bg `#F5F7F7` | 5.20:1 | AA text |
| | | Subtle `--pt-danger-subtle` `#FCE8EA` | 4.76:1 | AA text |
| `--pt-danger-solid` / on-color | `#C42B3C` / `#FFFFFF` | | 5.59:1 | AA text |
| `--pt-info` | `#0B6E8A` | Surface `#FFFFFF` | 5.81:1 | AA text |
| | | Bg `#F5F7F7` | 5.41:1 | AA text |
| | | Subtle `--pt-info-subtle` `#E3F5FA` | 5.18:1 | AA text |
| `--pt-info-solid` / on-color | `#0B6E8A` / `#FFFFFF` | | 5.81:1 | AA text |

**Dark mode**

| Token | Value | Against | Ratio | Result |
|---|---|---|---|---|
| `--pt-success` | `#3FD28F` | Night `#000012` | 10.73:1 | AA and AAA text |
| | | Navy deep `#050B1F` | 10.08:1 | AA and AAA text |
| | | Raised `#0B1530` | 9.30:1 | AA and AAA text |
| | | Subtle `#0A2A20` | 7.93:1 | AA and AAA text |
| `--pt-success-solid` / on-color | `#3FD28F` / `#000012` | | 10.73:1 | AA and AAA text |
| `--pt-warning` | `#F2B544` | Night `#000012` | 11.37:1 | AA and AAA text |
| | | Navy deep `#050B1F` | 10.68:1 | AA and AAA text |
| | | Raised `#0B1530` | 9.85:1 | AA and AAA text |
| | | Subtle `#2E2108` | 8.58:1 | AA and AAA text |
| `--pt-warning-solid` / on-color | `#F2B544` / `#000012` | | 11.37:1 | AA and AAA text |
| `--pt-danger` | `#FF7A86` | Night `#000012` | 8.31:1 | AA and AAA text |
| | | Navy deep `#050B1F` | 7.80:1 | AA and AAA text |
| | | Raised `#0B1530` | 7.20:1 | AA and AAA text |
| | | Subtle `#33111A` | 6.78:1 | AA text |
| `--pt-danger-solid` / on-color | `#FF7A86` / `#000012` | | 8.31:1 | AA and AAA text |
| `--pt-info` | `#6AD7EC` | Night `#000012` | 12.44:1 | AA and AAA text |
| | | Navy deep `#050B1F` | 11.68:1 | AA and AAA text |
| | | Raised `#0B1530` | 10.78:1 | AA and AAA text |
| | | Subtle `#062A33` | 9.05:1 | AA and AAA text |
| `--pt-info-solid` / on-color | `#6AD7EC` / `#000012` | | 12.44:1 | AA and AAA text |

Rules:
- Status is never conveyed by color alone: every status color is paired with an icon and a text label (WCAG 1.4.1).
- Success and danger are not used as the only distinction between two series (red and green confusion); charts use the chart palette.
- Solid fills in dark mode use dark text (`#000012`), never white.

### 4.5 Chart palette (colorblind-safe)

Built from blue, cyan, indigo and navy, plus one derived warm amber and a neutral slate, so adjacent series differ in both hue family (cool versus warm) and lightness. Light and dark variants keep the same order.

| Series | Light | Ratio vs `#FFFFFF` | Dark | Ratio vs `#050B1F` |
|---|---|---|---|---|
| 1 | Blue `#005EFF` | 5.19:1 | Blue light `#4D8DFF` | 6.12:1 |
| 2 | Amber `#B86E00` (derived) | 3.99:1 | Amber `#E0952B` (derived) | 7.92:1 |
| 3 | Navy `#0E285C` | 14.22:1 | Ice `#C9D6FF` (derived) | 13.54:1 |
| 4 | Teal `#0F8FA8` (cyan-derived) | 3.81:1 | Cyan `#6AD7EC` | 11.68:1 |
| 5 | Slate `#5E6E78` (derived neutral) | 5.28:1 | Slate `#A9B6C2` (derived neutral) | 9.46:1 |
| 6 | Indigo `#3358DD` | 5.86:1 | Indigo light `#8EA2FF` (derived) | 8.16:1 |

Rules:
- Every series color reaches at least 3:1 against its surface (WCAG 1.4.11 graphical objects).
- Maximum 6 series; beyond that, group into "Other" or use small multiples.
- Series 1 and 6 (blue and indigo) are both blue; when both appear, series 6 uses a dashed line or hatched fill.
- Direct labels or a legend with shape markers on every chart; values available as a table (accessible alternative and keyboard access).
- Plan vs. actual: plan in slate, actual in blue; over budget is marked with the danger color plus an icon and label, never color alone.
- Sequential scales (heatmaps, utilization): single-hue blue ramp from `#E3ECFF` to `#0E285C` in light, `#0B1530` to `#6AD7EC` in dark.
- The palette is verified with a color vision deficiency simulation (protanopia, deuteranopia, tritanopia) in the design system before 0.9 sign-off.

### 4.6 White-label color validation

When a tenant changes color tokens, the admin UI computes contrast for every role pair in 4.3 and 4.4 and refuses to save a theme that fails WCAG 2.2 AA, showing the failing pair and a suggested adjustment. A CI test runs the same check on the default theme.

## 5. Gradients

| Name | Value | Allowed | Never |
|---|---|---|---|
| Beam | `linear-gradient(160deg, #0E285C 0%, #1646B8 58%, #2C7BFF 100%)` | Login screen, onboarding wizard backgrounds, empty states, app icon background, product site hero | Behind tables, forms, timesheets, charts or any dense data |
| Signal | `linear-gradient(90deg, #3358DD 0%, #6AD7EC 100%)` (direction flips in RTL) | Progress bars, highlights, roadmap bars, active step indicators | Text fills, backgrounds of text blocks, status meaning (it does not mean success) |

Contrast notes: white text on Beam measures 14.22:1 on `#0E285C`, 8.07:1 on `#1646B8` and 3.90:1 on `#2C7BFF`, so body text must sit on the first 58 % of the gradient (top-left in LTR); large text only near the light end. Signal bars always show their value as text next to the bar, because the cyan end has low contrast on light surfaces (1.67:1); the bar sits on a `--pt-color-line` track. In `prefers-contrast: more`, gradients are replaced by solid `--pt-navy` (Beam) and `--pt-indigo` (Signal).

## 6. Typography

All fonts are self-hosted, subsetted WOFF2 files under the SIL Open Font License. No font CDN.

| Role | Family | Weights | Notes |
|---|---|---|---|
| Display and headings (h1, h2, page titles, wordmark-adjacent text) | Mona Sans | 400 (regular) | Tight letter-spacing |
| UI and body | Golos Text | 400, 500, 600 | Base 14 px in dense views, 16 px in reading views |
| Arabic (`ar`) | Noto Naskh Arabic | 400, 600 | Replaces both families for Arabic script |
| Farsi (`fa`) | Vazirmatn | 400, 500, 600 | Replaces both families for Persian |
| Monospace (IDs, code, API keys) | System monospace stack | 400 | `ui-monospace, SFMono-Regular, Menlo, Consolas, monospace` |

Type scale:

| Token | Size / line height | Family | Letter-spacing | Use |
|---|---|---|---|---|
| `--pt-text-xs` | 12 / 16 px | Golos Text | 0 | Captions, table meta |
| `--pt-text-sm` | 13 / 18 px | Golos Text | 0 | Secondary UI |
| `--pt-text-base` | 14 / 20 px | Golos Text | 0 | Dense views (tables, timesheet, forms) |
| `--pt-text-md` | 16 / 24 px | Golos Text | 0 | Reading views (wiki, descriptions, docs) |
| `--pt-text-lg` | 18 / 26 px | Golos Text 600 | 0 | Section headings in dense views |
| `--pt-text-xl` | 20 / 28 px | Mona Sans | -0.02em | h3 |
| `--pt-text-2xl` | 24 / 32 px | Mona Sans | -0.02em | h2 |
| `--pt-text-3xl` | 32 / 40 px | Mona Sans | -0.04em | h1, page titles |
| `--pt-text-display` | 48 / 52 px | Mona Sans | -0.04em | Login, onboarding, empty states |

Rules:
- **Tabular numbers** (`font-variant-numeric: tabular-nums`) for all amounts, hours, durations, dates, quantities and IDs, in tables and in running text. Amounts are right-aligned in LTR and left-aligned in RTL tables (end-aligned via logical properties).
- Arabic and Persian: letter-spacing is always 0 (negative tracking breaks joining), line height at least 1.6, no uppercase transforms, font size +1 step for body text.
- Uppercase is reserved for the wordmark and short overlines; never for buttons or sentences.
- Minimum text size 12 px; body text never below 14 px.

## 7. Shape, spacing, elevation and motion

| Token | Value | Use |
|---|---|---|
| `--pt-radius-button` | 16 px | Pill-shaped buttons (32 to 40 px height) |
| `--pt-radius-input` | 12 px | Inputs, selects, text areas |
| `--pt-radius-card` | 22 px | Cards, panels, dialogs |
| `--pt-radius-sm` | 8 px | Tags, badges, small controls |
| `--pt-radius-full` | 9999 px | Avatars, icon chips, toggles |
| `--pt-space-*` | 4 px scale: 4, 8, 12, 16, 20, 24, 32, 40, 48, 64 | Spacing (comfortable density); compact density reduces vertical paddings by one step |
| `--pt-shadow-1` | `0 1px 2px #000D0E14, 0 1px 1px #000D0E0A` | Cards (light) |
| `--pt-shadow-2` | `0 8px 24px #000D0E1F` | Popovers, menus, dialogs (light); dark mode uses raised surfaces instead of shadows |
| `--pt-ease-enter` | `cubic-bezier(.22, 1, .36, 1)` | Elements entering |
| `--pt-ease-move` | `cubic-bezier(.65, 0, .35, 1)` | Elements moving or resizing |
| `--pt-duration-fast` | 150 ms | Hover, press, small state changes |
| `--pt-duration-base` | 200 ms | Menus, popovers, toasts |
| `--pt-duration-slow` | 250 ms | Dialogs, drawers, page-level transitions |

- Primary buttons may carry a small trailing icon chip (circle, `--pt-radius-full`, 24 px) as on projektra.net; the chip sits at the inline end and mirrors in RTL if the icon is directional.
- Generous whitespace; one primary button per view region.
- `prefers-reduced-motion: reduce` sets all durations to 0 ms except opacity fades (max 100 ms); no parallax, no auto-playing animation.

## 8. Iconography

- One icon set: Lucide (ISC license), self-hosted as tree-shaken Vue components. No icon fonts, no CDN.
- Sizes 16, 20, 24 px; stroke 1.75 px at 20 and 24 px; icons inherit `currentColor`.
- Icon-only buttons need an accessible name (translated string) and a tooltip; hit target at least 24 x 24 px (WCAG 2.5.8), 40 x 40 px on touch.
- **Mirroring in RTL**:
  - Mirror: arrows, chevrons, back/forward, undo/redo, reply, send, indent/outdent, list bullets with direction, progress and timeline direction, breadcrumbs separators, sidebar collapse, "external link" arrow, text alignment icons.
  - Do not mirror: clock and timer (clocks run clockwise everywhere), search, play/pause (media convention), checkmark, plus/minus, close, calendar, user, settings, brand marks and logos, charts' numeric axes values, slash in code.
  - Implementation: a `mirror` prop on the `Icon` component driven by a registry of directional icon names; CSS `:dir(rtl) .pt-icon--mirror { transform: scaleX(-1); }`; Playwright RTL screenshots cover it.

## 9. Tone of voice

Calm, clear, problem-first, no hype. Short sentences. Verbs first on buttons. The result matters, not the tool. Address users formally in German (Sie) and with formal plural forms in Bosnian; English stays neutral and direct. No exclamation marks in UI copy, no emojis, no jokes in error messages, no blame ("you entered wrong data").

| Situation | en | bs | de |
|---|---|---|---|
| Primary button | Book time | Evidentirajte vrijeme | Zeit buchen |
| Primary button | Create invoice | Kreirajte fakturu | Rechnung erstellen |
| Empty state | No time booked this week. Start the timer or add an entry. | Ove sedmice nema evidentiranog vremena. Pokrenite tajmer ili dodajte unos. | Diese Woche ist noch keine Zeit gebucht. Starten Sie den Timer oder fügen Sie einen Eintrag hinzu. |
| Inline validation | Enter a duration, for example 1:30. | Unesite trajanje, na primjer 1:30. | Geben Sie eine Dauer ein, zum Beispiel 1:30. |
| Error with fix | The invoice was not sent. The customer has no email address. Add one in the customer record. | Faktura nije poslana. Klijent nema e-mail adresu. Dodajte je u podacima o klijentu. | Die Rechnung wurde nicht gesendet. Für den Kunden ist keine E-Mail-Adresse hinterlegt. Ergänzen Sie sie im Kundendatensatz. |
| Undo toast | Time entry deleted. Undo | Unos vremena je obrisan. Poništi | Zeiteintrag gelöscht. Rückgängig |
| Success | Service report created. 42 entries, 63:15 hours. | Izvještaj o radu je kreiran. 42 unosa, 63:15 sati. | Leistungsnachweis erstellt. 42 Einträge, 63:15 Stunden. |

Avoid: "Oops, something went wrong", "Awesome!", "Supercharge your workflow", "Simply click here", technical jargon such as "HTTP 422" or "null" in user-facing text.

Note: Bosnian example strings show style only. The authoritative translations live in `lang/bs/` and follow the i18n glossary; buttons in the product may use the shorter infinitive style if the glossary decides so.

## 10. Logo usage

- The official PROJEKTRA "P" mark and wordmark SVGs are supplied by PROJEKTRA and stored in `resources/brand/` (assumption A-02). They are never redrawn, recolored outside the approved variants, stretched, rotated, outlined, given effects or placed on busy images.
- App icon: the "P" mark in white on the Beam gradient, rounded square (radius 22 % of size).
- Product wordmark "PROJEKTRACK" is set in the same style as the company wordmark and supplied as SVG by PROJEKTRA.
- Clear space: at least the height of the "P" mark's counter on every side (exact value fixed once assets arrive). Minimum size: mark 16 px, wordmark 80 px wide on screen.
- Approved variants: ink on light, white on Night/Navy Deep/Beam.
- In the application (23.11.2026): the supplied white mark (`resources/brand/mark-white.png`, scaled only) on Beam stands in the navigation after sign-in, while neither tenant nor instance has a brand of its own. Before sign-in and as tab icon the neutral mark stays (S-0986). SVG originals and the product wordmark are still open (A-02).
- **Placeholder until assets arrive**: a neutral rounded-square tile (`--pt-color-line` fill, `--pt-color-text-muted` letter "P" set in Mona Sans) and the text wordmark "PROJEKTRACK" in Mona Sans, stored under `resources/brand/placeholder/` and labeled as placeholder in the design system. Listed as open item; replacing it requires no code change because all usages read from the brand configuration.

## 11. CSS custom properties

```css
/* resources/css/tokens.css */
:root {
  /* Brand constants */
  --pt-ink: #000d0e;
  --pt-bg: #f5f7f7;
  --pt-surface: #ffffff;
  --pt-line: #000d0e1a;
  --pt-blue: #005eff;
  --pt-blue-light: #4d8dff;
  --pt-navy: #0e285c;
  --pt-indigo: #3358dd;
  --pt-cyan: #6ad7ec;
  --pt-navy-deep: #050b1f;
  --pt-night: #000012;
  --pt-gradient-beam: linear-gradient(160deg, #0e285c 0%, #1646b8 58%, #2c7bff 100%);
  --pt-gradient-signal: linear-gradient(to right, #3358dd 0%, #6ad7ec 100%);

  /* Roles: light (default) */
  color-scheme: light;
  --pt-color-bg: var(--pt-bg);
  --pt-color-surface: var(--pt-surface);
  --pt-color-surface-raised: var(--pt-surface);
  --pt-color-text: var(--pt-ink);
  --pt-color-text-muted: #4a5a63;
  --pt-color-heading: var(--pt-navy);
  --pt-color-line: var(--pt-line);
  --pt-color-line-strong: #7a878d;
  --pt-color-primary: var(--pt-blue);
  --pt-color-on-primary: #ffffff;
  --pt-color-link: var(--pt-blue);
  --pt-color-focus: var(--pt-blue);
  --pt-color-secondary: var(--pt-navy);
  --pt-color-accent: var(--pt-cyan);
  --pt-color-sidebar: var(--pt-navy);

  --pt-success: #0a7a4f;  --pt-success-subtle: #e6f4ee;  --pt-success-solid: #0a7a4f;  --pt-on-success: #ffffff;
  --pt-warning: #9a5b00;  --pt-warning-subtle: #fdf1dc;  --pt-warning-solid: #f2b544;  --pt-on-warning: #000d0e;
  --pt-danger:  #c42b3c;  --pt-danger-subtle:  #fce8ea;  --pt-danger-solid:  #c42b3c;  --pt-on-danger:  #ffffff;
  --pt-info:    #0b6e8a;  --pt-info-subtle:    #e3f5fa;  --pt-info-solid:    #0b6e8a;  --pt-on-info:    #ffffff;

  --pt-chart-1: #005eff; --pt-chart-2: #b86e00; --pt-chart-3: #0e285c;
  --pt-chart-4: #0f8fa8; --pt-chart-5: #5e6e78; --pt-chart-6: #3358dd;

  /* Typography */
  --pt-font-display: "Mona Sans", "Golos Text", system-ui, sans-serif;
  --pt-font-body: "Golos Text", system-ui, sans-serif;
  --pt-font-mono: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;

  /* Shape */
  --pt-radius-button: 16px;
  --pt-radius-input: 12px;
  --pt-radius-card: 22px;
  --pt-radius-sm: 8px;
  --pt-radius-full: 9999px;
  --pt-shadow-1: 0 1px 2px #000d0e14, 0 1px 1px #000d0e0a;
  --pt-shadow-2: 0 8px 24px #000d0e1f;

  /* Motion */
  --pt-ease-enter: cubic-bezier(.22, 1, .36, 1);
  --pt-ease-move: cubic-bezier(.65, 0, .35, 1);
  --pt-duration-fast: 150ms;
  --pt-duration-base: 200ms;
  --pt-duration-slow: 250ms;
}

:root[data-theme="dark"] {
  color-scheme: dark;
  --pt-color-bg: var(--pt-night);
  --pt-color-surface: var(--pt-navy-deep);
  --pt-color-surface-raised: #0b1530;
  --pt-color-text: #e6ecf5;
  --pt-color-text-muted: #9aa8bf;
  --pt-color-heading: #e6ecf5;
  --pt-color-line: #e6ecf51f;
  --pt-color-line-strong: #6e7fa0;
  --pt-color-primary: var(--pt-blue);
  --pt-color-on-primary: #ffffff;
  --pt-color-link: var(--pt-blue-light);
  --pt-color-focus: var(--pt-blue-light);
  --pt-color-secondary: #e6ecf5;
  --pt-color-accent: var(--pt-cyan);
  --pt-color-sidebar: var(--pt-navy-deep);

  --pt-success: #3fd28f;  --pt-success-subtle: #0a2a20;  --pt-success-solid: #3fd28f;  --pt-on-success: #000012;
  --pt-warning: #f2b544;  --pt-warning-subtle: #2e2108;  --pt-warning-solid: #f2b544;  --pt-on-warning: #000012;
  --pt-danger:  #ff7a86;  --pt-danger-subtle:  #33111a;  --pt-danger-solid:  #ff7a86;  --pt-on-danger:  #000012;
  --pt-info:    #6ad7ec;  --pt-info-subtle:    #062a33;  --pt-info-solid:    #6ad7ec;  --pt-on-info:    #000012;

  --pt-chart-1: #4d8dff; --pt-chart-2: #e0952b; --pt-chart-3: #c9d6ff;
  --pt-chart-4: #6ad7ec; --pt-chart-5: #a9b6c2; --pt-chart-6: #8ea2ff;

  --pt-shadow-1: none;
  --pt-shadow-2: 0 0 0 1px #e6ecf51f;
}

/* Theme "system" follows the OS setting; the dark values above are mirrored here. */
@media (prefers-color-scheme: dark) {
  :root[data-theme="system"] { /* same declarations as :root[data-theme="dark"], generated at build time */ }
}

:root:dir(rtl) { --pt-gradient-signal: linear-gradient(to left, #3358dd 0%, #6ad7ec 100%); }
:lang(ar) { --pt-font-display: "Noto Naskh Arabic", system-ui, sans-serif; --pt-font-body: "Noto Naskh Arabic", system-ui, sans-serif; }
:lang(fa) { --pt-font-display: "Vazirmatn", system-ui, sans-serif; --pt-font-body: "Vazirmatn", system-ui, sans-serif; }
:is(:lang(ar), :lang(fa)) * { letter-spacing: 0 !important; }

.pt-num { font-variant-numeric: tabular-nums; }

@media (prefers-reduced-motion: reduce) {
  :root { --pt-duration-fast: 0ms; --pt-duration-base: 0ms; --pt-duration-slow: 0ms; }
}
```

The build step generates the `data-theme="system"` block from the dark block so the two never drift; a CI test compares them.

## 12. Tailwind theme (Tailwind CSS 4)

```css
/* resources/css/app.css */
@import "tailwindcss";
@import "./tokens.css";

@custom-variant dark (&:where([data-theme="dark"], [data-theme="dark"] *));

@theme inline {
  --color-bg: var(--pt-color-bg);
  --color-surface: var(--pt-color-surface);
  --color-surface-raised: var(--pt-color-surface-raised);
  --color-text: var(--pt-color-text);
  --color-text-muted: var(--pt-color-text-muted);
  --color-heading: var(--pt-color-heading);
  --color-line: var(--pt-color-line);
  --color-line-strong: var(--pt-color-line-strong);
  --color-primary: var(--pt-color-primary);
  --color-on-primary: var(--pt-color-on-primary);
  --color-link: var(--pt-color-link);
  --color-focus: var(--pt-color-focus);
  --color-secondary: var(--pt-color-secondary);
  --color-accent: var(--pt-color-accent);
  --color-sidebar: var(--pt-color-sidebar);

  --color-success: var(--pt-success);
  --color-success-subtle: var(--pt-success-subtle);
  --color-success-solid: var(--pt-success-solid);
  --color-on-success: var(--pt-on-success);
  --color-warning: var(--pt-warning);
  --color-warning-subtle: var(--pt-warning-subtle);
  --color-warning-solid: var(--pt-warning-solid);
  --color-on-warning: var(--pt-on-warning);
  --color-danger: var(--pt-danger);
  --color-danger-subtle: var(--pt-danger-subtle);
  --color-danger-solid: var(--pt-danger-solid);
  --color-on-danger: var(--pt-on-danger);
  --color-info: var(--pt-info);
  --color-info-subtle: var(--pt-info-subtle);
  --color-info-solid: var(--pt-info-solid);
  --color-on-info: var(--pt-on-info);

  --color-chart-1: var(--pt-chart-1);
  --color-chart-2: var(--pt-chart-2);
  --color-chart-3: var(--pt-chart-3);
  --color-chart-4: var(--pt-chart-4);
  --color-chart-5: var(--pt-chart-5);
  --color-chart-6: var(--pt-chart-6);

  --font-display: var(--pt-font-display);
  --font-sans: var(--pt-font-body);
  --font-mono: var(--pt-font-mono);

  --text-xs: 12px;   --text-xs--line-height: 16px;
  --text-sm: 13px;   --text-sm--line-height: 18px;
  --text-base: 14px; --text-base--line-height: 20px;
  --text-md: 16px;   --text-md--line-height: 24px;
  --text-lg: 18px;   --text-lg--line-height: 26px;
  --text-xl: 20px;   --text-xl--line-height: 28px;   --text-xl--letter-spacing: -0.02em;
  --text-2xl: 24px;  --text-2xl--line-height: 32px;  --text-2xl--letter-spacing: -0.02em;
  --text-3xl: 32px;  --text-3xl--line-height: 40px;  --text-3xl--letter-spacing: -0.04em;
  --text-display: 48px; --text-display--line-height: 52px; --text-display--letter-spacing: -0.04em;

  --radius-button: var(--pt-radius-button);
  --radius-input: var(--pt-radius-input);
  --radius-card: var(--pt-radius-card);
  --radius-sm: var(--pt-radius-sm);

  --shadow-1: var(--pt-shadow-1);
  --shadow-2: var(--pt-shadow-2);

  --ease-enter: var(--pt-ease-enter);
  --ease-move: var(--pt-ease-move);

  --spacing: 4px;
}

@utility bg-beam { background-image: var(--pt-gradient-beam); }
@utility bg-signal { background-image: var(--pt-gradient-signal); }
```

Usage examples: `bg-surface text-text rounded-card shadow-1`, `bg-primary text-on-primary rounded-button px-4 h-10`, `text-danger`, `bg-warning-subtle text-warning`, `ps-4 pe-2` (logical properties only; physical `pl-`/`pr-`/`left-`/`right-` utilities are banned by lint).
