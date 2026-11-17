# ADR-0001: Technology stack and progressive infrastructure

- **Status:** Accepted, 16.11.2026
- **Deciders:** Product owner, solution architecture
- **Supersedes:** version numbers in assumption A-06 (see "Deviations from A-06" below)
- **Related:** ADR-0002 (module system), ADR-0003 (licensing), ADR-0004 (money and tax), ADR-0005 (i18n and RTL), ADR-0006 (multi-tenancy), ADR-0008 (e-invoicing)

## Context

Projektrack is a modular monolith for projects, time tracking, service reports, quotes, invoicing with e-invoicing and licensing. The owner and licensor is PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik. Three constraints dominate the stack choice:

1. **Plain AMP must work.** The Community edition must install from a release ZIP on shared hosting (Apache or Nginx, PHP, MySQL/MariaDB, one cron entry) without Composer, Node, Redis, Java or Docker on the server, like WordPress or Moodle.
2. **Enterprise options must work too.** PostgreSQL, Redis/Valkey, Meilisearch, Gotenberg, WebSockets and S3 storage are used automatically when configured (Docker, Kubernetes, managed instances, SaaS).
3. **Release 0.9 ships on 01.12.2026** with a small team. The stack must be productive on day one, with mature packages for auth, queues, PDF, DOCX, XLSX, money and e-invoicing.

The first customer license is delivered as a managed dedicated instance on PROJEKTRA servers (confirmed decision C-03), so the Docker profile is the delivery critical path, but the AMP ZIP still ships in 0.9 and is tested in CI.

## Decision

### 1. Stack overview

| Layer | Choice |
|---|---|
| Runtime | PHP 8.4 minimum, PHP 8.5 recommended and used in the Docker image |
| Backend framework | Laravel 13 (modular monolith, modules under `/modules` and `/ee/modules`, see ADR-0002) |
| Frontend | Vue 3.5 + TypeScript 6.0 + Inertia.js 3 (Laravel adapter and Vue adapter), built with Vite 8 |
| UI | Tailwind CSS 4 (logical properties only, see ADR-0005), shadcn-vue 2 components on Reka UI 2 primitives |
| Rich text | TipTap 3 (ProseMirror); Y.js reserved for co-editing in 2.0 |
| Databases | MySQL 8.4 LTS and 9.7 LTS, MariaDB 10.11 / 11.4 / 11.8 / 12.3 LTS, PostgreSQL 16 to 18 |
| Queue, cache, locks | Database drivers + cron on AMP; Redis or Valkey + Horizon when available |
| Search | Database full-text adapter on AMP; Meilisearch via Laravel Scout when available |
| Real-time | Inertia polling on AMP; Laravel Reverb (WebSockets) when available |
| Files | Local filesystem; S3-compatible (MinIO) when configured |
| PDF | mPDF 8 (pure PHP, RTL shaping, PDF/A-3 with associated files, see ADR-0008); Gotenberg optional for DOCX/HTML to PDF |
| DOCX | PhpWord TemplateProcessor |
| XLSX | PhpSpreadsheet (real formulas) |
| Money | brick/money (integer minor units, see ADR-0004) |
| E-invoicing | horstoeko/zugferd (CII) + horstoeko/zugferdublbridge (UBL) behind an own adapter (see ADR-0008) |
| Auth | Laravel Fortify + Sanctum, Argon2id, TOTP, WebAuthn via web-auth/webauthn-lib |
| Crypto for licensing | ext-sodium (Ed25519), paragonie/sodium_compat as fallback (see ADR-0003) |
| i18n | ext-intl `MessageFormatter` (PHP) and FormatJS `intl-messageformat` (JS), see ADR-0005 |
| Tests | Pest 5, Vitest 5, Playwright |
| Static analysis and style | Larastan 3 / PHPStan 2 at max level, Pint, ESLint 10, Prettier 3, vue-tsc 3 |
| Build toolchain (CI and developer machines only) | Node.js 24 LTS, Composer 2 |

### 2. Pinned versions (checked 16.11.2026)

Versions below are the latest stable releases found on 16.11.2026 from the package registries and project release pages. Composer and npm lock files pin exact versions when the repository is scaffolded; the constraints column shows the range the lock file may resolve within. Anything marked **verify at scaffold** could not be confirmed from a primary source today and must be re-checked when the repository is created.

| Component | Pinned version | Constraint | Released | Source | Notes |
|---|---|---|---|---|---|
| PHP | 8.5.12 (Docker); 8.4.27 minimum line | `>=8.4 <8.6` | 8.5.0: 20.11.2025 | [php.net supported versions](https://www.php.net/supported-versions.php), [endoflife.date/php](https://endoflife.date/php) | 8.4 active support until 31.12.2026, security until 31.12.2028; 8.5 active until 31.12.2027. PHP 8.6 is at RC7 today: not supported until mPDF and Pest declare it |
| Laravel framework | 13.42.0 | `^13.42` | 10.11.2026 (13.0.0: 17.03.2026) | [packagist laravel/framework](https://packagist.org/packages/laravel/framework), [Laravel 13 release notes](https://laravel.com/docs/13.x/releases) | Requires PHP `^8.3`. Bug fixes until Q3 2027, security fixes until Q1 2028 |
| Inertia.js (Laravel) | inertiajs/inertia-laravel 3.7.0 | `^3.7` | 02.11.2026 | [packagist](https://packagist.org/packages/inertiajs/inertia-laravel) | Major 3 |
| Inertia.js (Vue) | @inertiajs/vue3 3.11.0 | `^3.11` | 02.11.2026 | [npm](https://www.npmjs.com/package/@inertiajs/vue3) | |
| Vue | 3.5.45 | `~3.5.45` | 05.11.2026 | [npm vue](https://www.npmjs.com/package/vue) | |
| TypeScript | 6.0.3 | `~6.0.3` | 2026 | [npm typescript](https://www.npmjs.com/package/typescript) | TypeScript 7.0.2 (native Go compiler) is "latest" on npm, but vue-tsc cannot run on it yet; Vue support depends on TS 7.1 content mappers and Vue language tools v4 (alpha). See [vuejs/language-tools discussion 6121](https://github.com/vuejs/language-tools/discussions/6121) |
| vue-tsc | 3.3.14 | `~3.3.14` | 29.10.2026 | [GitHub vuejs/language-tools](https://github.com/vuejs/language-tools/releases) | Peer `typescript >=5.0.0` |
| Vite | 8.3.8 | `~8.3.8` | 11.11.2026 | [npm vite](https://www.npmjs.com/package/vite) | laravel-vite-plugin 3.2.1 requires `vite ^8` and Node `^20.19 or >=22.12` |
| Tailwind CSS | 4.3.5 (+ @tailwindcss/vite 4.3.5) | `~4.3.5` | 06.11.2026 | [npm tailwindcss](https://www.npmjs.com/package/tailwindcss) | Logical utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`) |
| shadcn-vue | 2.9.0 (CLI, components are copied into the repo) | `2.9.0` | 28.10.2026 | [npm shadcn-vue](https://www.npmjs.com/package/shadcn-vue) | Depends on reka-ui `^2.10.5`, tailwindcss `^4.3.3` |
| Reka UI | 2.11.0 | `~2.11.0` | 05.10.2026 | [npm reka-ui](https://www.npmjs.com/package/reka-ui) | Date pickers use @internationalized/date 3.12.4 (Persian and Islamic calendars available) |
| TipTap | @tiptap/core and @tiptap/vue-3 3.34.0 | `~3.34.0` | 13.11.2026 | [npm @tiptap/core](https://www.npmjs.com/package/@tiptap/core) | Wiki is not in 0.9; used for rich text fields |
| MySQL | 8.4.13 LTS and 9.7.4 LTS | 8.4 and 9.7 LTS lines | 9.7: 21.04.2026 | [endoflife.date/mysql](https://endoflife.date/mysql) | 8.4 EOL 30.04.2032, 9.7 EOL 30.04.2034. Innovation releases (26.x) not supported |
| MariaDB | 10.11.20, 11.4.14, 11.8.10, 12.3.4 (all LTS) | LTS lines only | 12.3: 28.05.2026 | [endoflife.date/mariadb](https://endoflife.date/mariadb) | 10.11 kept as floor because shared hosts still run it (EOL 16.02.2028) |
| PostgreSQL | 16.16, 17.12, 18.7 | 16 to 18 | 18.0: 25.09.2025 | [endoflife.date/postgresql](https://endoflife.date/postgresql) | |
| Redis | 8.10.3 | 8.x | 26.10.2026 | [GitHub redis/redis](https://github.com/redis/redis/releases) | License is RSALv2/SSPLv1/AGPLv3; acceptable for customers who already run it |
| Valkey | 9.1.3 | 9.x | 26.10.2026 | [GitHub valkey-io/valkey](https://github.com/valkey-io/valkey/releases) | BSD-3-Clause; **default in our Docker bundle** |
| Laravel Horizon | 5.51.0 | `^5.51` | 02.11.2026 | [packagist laravel/horizon](https://packagist.org/packages/laravel/horizon) | Needs Redis/Valkey and ext-pcntl |
| Laravel Reverb | 1.13.0 | `^1.13` | 19.10.2026 | [packagist laravel/reverb](https://packagist.org/packages/laravel/reverb) | Client: laravel-echo 2.6.3, @laravel/echo-vue 2.6.3 |
| Laravel Scout | 11.10.0 | `^11.10` | 10.11.2026 | [packagist laravel/scout](https://packagist.org/packages/laravel/scout) | |
| Meilisearch | 1.57.0 (server), meilisearch-php 1.17.0 | 1.x | 16.11.2026 | [GitHub meilisearch](https://github.com/meilisearch/meilisearch/releases) | Pin server image to a tested minor, upgrade deliberately |
| Gotenberg | 9.5.1 | 9.x | 14.11.2026 | [GitHub gotenberg](https://github.com/gotenberg/gotenberg/releases) | Major 9; our client must be tested against the 9.x API |
| mPDF | 8.3.1 | `^8.3` | 11.03.2026 | [packagist mpdf/mpdf](https://packagist.org/packages/mpdf/mpdf) | Declares PHP up to `~8.5.0`. Supports `PDFAversion` "3-B", `SetAssociatedFiles()` and `SetAdditionalXmpRdf()` (verified in [source](https://github.com/mpdf/mpdf/blob/development/src/Mpdf.php)) |
| PhpWord | 1.4.0 | `^1.4` | 05.06.2025 | [packagist phpoffice/phpword](https://packagist.org/packages/phpoffice/phpword) | No release for 17 months: maintenance risk, see Consequences |
| PhpSpreadsheet | 5.11.0 | `^5.11` | 08.11.2026 | [packagist phpoffice/phpspreadsheet](https://packagist.org/packages/phpoffice/phpspreadsheet) | |
| brick/money | 0.15.2 | `~0.15.2` | 29.09.2026 | [packagist brick/money](https://packagist.org/packages/brick/money) | Pre-1.0: minor versions may break; pin the minor |
| horstoeko/zugferd | 1.0.135 | `^1.0.135` | 08.11.2026 | [packagist](https://packagist.org/packages/horstoeko/zugferd), [GitHub](https://github.com/horstoeko/zugferd) | MIT, CII only. Official successor horstoeko/invoicesuite is 0.0.42 (pre-1.0) |
| horstoeko/zugferdublbridge | 1.0.20 | `^1.0.20` | 01.11.2026 | [packagist](https://packagist.org/packages/horstoeko/zugferdublbridge) | CII to UBL and back, for XRechnung UBL |
| Alternatives for UBL | num-num/ubl-invoice 2.4.1; easybill/e-invoicing 0.4.0; josemmo/einvoicing 0.3.1 | not installed | see ADR-0008 | [packagist](https://packagist.org/packages/num-num/ubl-invoice) | Compared in ADR-0008 |
| KoSIT validator (optional service) | 1.6.3, XRechnung configuration 2026-08-31 | Java 11+ | 20.08.2026 | [GitHub itplr-kosit/validator](https://github.com/itplr-kosit/validator/releases) | Apache-2.0; runs as HTTP daemon in Docker profile only |
| Pest | 5.3.6 | `^5.3` | 14.11.2026 | [packagist pestphp/pest](https://packagist.org/packages/pestphp/pest) | **Requires PHP `^8.4`**: one reason for the 8.4 minimum |
| Vitest | 5.1.0 | `~5.1.0` | 02.11.2026 | [npm vitest](https://www.npmjs.com/package/vitest) | |
| Playwright | @playwright/test 1.65.1 | `~1.65.1` | 13.11.2026 | [npm @playwright/test](https://www.npmjs.com/package/@playwright/test) | |
| Larastan | 3.15.0 (PHPStan 2.3.6) | `^3.15` | 11.11.2026 | [packagist larastan/larastan](https://packagist.org/packages/larastan/larastan) | Level max |
| Laravel Pint | 1.32.3 | `^1.32` | 11.11.2026 | [packagist laravel/pint](https://packagist.org/packages/laravel/pint) | |
| Laravel Fortify / Sanctum | 1.42.0 / 4.4.0 | `^1.42` / `^4.4` | 10/2026, 11/2026 | [packagist laravel/fortify](https://packagist.org/packages/laravel/fortify) | |
| WebAuthn | web-auth/webauthn-lib 5.3.12 | `^5.3` | 03.11.2026 | [packagist](https://packagist.org/packages/web-auth/webauthn-lib) | |
| sodium_compat | 2.5.2 | `^2.5` | 18.08.2026 | [packagist](https://packagist.org/packages/paragonie/sodium_compat) | Only used if ext-sodium is missing |
| intl-messageformat | 12.1.5 | `~12.1.5` | 08.11.2026 | [npm](https://www.npmjs.com/package/intl-messageformat) | ICU MessageFormat in the browser |
| ESLint / Prettier | 10.15.0 / 3.10.1 | exact | 11/2026 | [npm eslint](https://www.npmjs.com/package/eslint) | eslint-plugin-vue 10.12.1 |
| Node.js (build only) | 24.x LTS | `>=24 <25` | LTS since 28.10.2025 | [endoflife.date/nodejs](https://endoflife.date/nodejs) | Node 26 LTS available since 28.10.2026; switch in 1.0 after toolchain check |
| Fonts | Mona Sans 2.0.27, Vazirmatn 33.003, Golos Text, Noto Naskh Arabic | files vendored | | [Mona Sans](https://github.com/github/mona-sans/releases), [Vazirmatn](https://github.com/rastikerdar/vazirmatn/releases) | Golos Text and Noto Naskh Arabic: **verify at scaffold** (no tagged release found; take OFL sources from the upstream repositories) |
| CLDR (reference) | 49 | | 21.10.2026 | [GitHub unicode-org/cldr](https://github.com/unicode-org/cldr/releases) | Plural rules come from ICU in PHP intl and the browser; CI checks parity (ADR-0005) |
| Weblate (translation platform) | 2026.11.2 | self-hosted | 12.11.2026 | [GitHub WeblateOrg/weblate](https://github.com/WeblateOrg/weblate/releases) | Not part of the product runtime |

All "Released" dates were read from the registries on 16.11.2026. Registry data used: `repo.packagist.org/p2/<package>.json`, `registry.npmjs.org/<package>`, GitHub release API, endoflife.date API.

### 3. Deviations from assumption A-06

A-06 assumed Laravel 12, PHP 8.3+, Inertia 2, MySQL 8.4 LTS and MariaDB 11.4 LTS. Verified today:

- **Laravel 13 instead of 12.** Laravel 12 bug-fix support ended 13.08.2026 and security support ends 24.02.2027; Laravel 13 is supported until Q1 2028.
- **Inertia 3 instead of 2.** Inertia 3.x is the current major for both the Laravel and the Vue adapter.
- **PHP 8.4 minimum instead of 8.3.** PHP 8.3 is security-only and ends 31.12.2027 (inside the life of 1.x LTS releases), and Pest 5 requires PHP 8.4. CI runs on the minimum (8.4) and the recommended (8.5) version. This is within A-06's "8.3 or newer".
- **MySQL 9.7 LTS added, MariaDB 10.11, 11.8 and 12.3 LTS added.** Shared hosts commonly run MariaDB 10.11; managed instances use the newest LTS.

A-06 should be updated in `assumptions.md` to point to this ADR.

### 4. Database support and CI matrix

- Supported: MySQL 8.4 LTS and 9.7 LTS; MariaDB 10.11, 11.4, 11.8 and 12.3 LTS; PostgreSQL 16, 17 and 18. SQLite is used only for fast unit tests, never as a production target.
- CI matrix on every pull request: PHP 8.4 + MySQL 8.4, PHP 8.4 + MariaDB 10.11 (floor), PHP 8.5 + MariaDB 12.3, PHP 8.5 + PostgreSQL 18. Nightly: the full matrix including MySQL 9.7 and PostgreSQL 16.
- Vendor-specific SQL only inside a tested adapter layer (full-text search, JSON path queries, locking hints). Character set `utf8mb4` with `utf8mb4_unicode_ci` on MySQL/MariaDB; `UTF8` with ICU collation on PostgreSQL.

### 5. Progressive infrastructure fallback matrix

Every optional service is detected from configuration at boot and has a working fallback. The admin "System status" screen shows which profile is active for each capability. Detection never makes outbound calls to third parties.

| Capability | AMP baseline (always works) | Upgrade when available | Detection | Behavior difference |
|---|---|---|---|---|
| Background jobs | `database` queue; cron runs `schedule:run` every minute; the scheduler runs `queue:work --stop-when-empty --max-time=50` | Redis/Valkey queue + Horizon workers (ext-pcntl, supervisor) | `QUEUE_CONNECTION=redis` and Redis ping | Latency up to 60 s on AMP vs. seconds; same jobs, same idempotency rules |
| Scheduler | One cron entry (`* * * * *`) | Same, or a container scheduler | Heartbeat row written each run | Admin warning if the heartbeat is older than 5 minutes |
| Cache | `file` or `database` | Redis/Valkey | `CACHE_STORE` | Performance only |
| Atomic locks (numbering, license check-in, imports) | Database cache locks + `SELECT ... FOR UPDATE` | Redis locks for non-financial locks | `CACHE_STORE` | Financial numbering always uses the database row lock (ADR-0004), never Redis |
| Sessions | `database` | Redis/Valkey | `SESSION_DRIVER` | None |
| Search | Database adapter: MySQL/MariaDB `FULLTEXT` (InnoDB), PostgreSQL `tsvector`, `LIKE` fallback | Meilisearch via Scout | `SCOUT_DRIVER=meilisearch` and health check | Typo tolerance and ranking only with Meilisearch |
| Real-time updates | Inertia polling (adaptive: 15 s active tab, paused in background tabs) | Laravel Reverb WebSockets | `BROADCAST_CONNECTION=reverb` | Same events; transport differs |
| PDF generation | mPDF in-process (including RTL shaping and PDF/A-3) | Gotenberg for DOCX to PDF (LibreOffice) and HTML to PDF (Chromium) | `GOTENBERG_URL` health check | DOCX templates render to PDF only with Gotenberg; on AMP the PDF comes from the HTML template via mPDF |
| DOCX / XLSX | PhpWord, PhpSpreadsheet | Same | none | None |
| File storage | Local disk outside the web root, signed download URLs | S3-compatible (MinIO, any S3) | `FILESYSTEM_DISK=s3` | None for users |
| E-invoice validation | XSD + Projektrack business rule set in PHP (ADR-0008) | KoSIT validator daemon (Java) | `KOSIT_VALIDATOR_URL` health check | Validation report says which level ran |
| Malware scan of uploads | Type, size and extension checks | ClamAV (`clamd`) | `CLAMAV_SOCKET` | Scan result shown on file |
| Mail | SMTP or `sendmail` | Same; inbound mailbox via IMAP (0.10) | Mail config test | None |
| Real browser E2E tooling, Node, Composer | Not needed on server (ZIP contains `vendor/` and built assets) | Docker image builds them | n/a | None |

PHP extensions required on AMP (checked by the web installer): `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql` or `pdo_pgsql`, `sodium` (or sodium_compat fallback), `xml`, `xmlwriter`, `simplexml`, `libxml`, `zip`, `bcmath` (recommended for brick/math performance). `intl` is mandatory: there is no fallback for ICU MessageFormat and CLDR plurals (ADR-0005).

### 6. Release artifacts (summary, details in infrastructure profiles)

- **AMP ZIP:** `vendor/` without dev dependencies, compiled assets, `public/` as web root, `.htaccess` with security and `X-Robots-Tag` headers, Nginx sample. Signed checksums.
- **Docker image:** PHP 8.5 FPM + Caddy, Valkey, Meilisearch, Gotenberg, MinIO, KoSIT validator (optional profile). Signed with cosign, published to GHCR.
- **Helm chart:** from 1.0.

## Alternatives considered

### Symfony 8 (instead of Laravel)
- Pros: excellent component quality, long-term-support releases, strong typing culture; symfony/symfony 8.1.11 is current ([packagist](https://packagist.org/packages/symfony/symfony)).
- Cons: Symfony 8 requires PHP `>=8.4.1`, similar to our choice, but it brings no first-party equivalent of Inertia integration, Horizon, Reverb, Fortify or Scout; we would assemble and maintain more glue code ourselves. Team productivity for a 01.12.2026 deadline is higher with Laravel. Laravel uses Symfony components underneath, so we keep most of the benefit.
- Rejected.

### Node.js / NestJS (TypeScript end to end)
- Pros: one language, strong real-time story, large ecosystem.
- Cons: **does not run on plain AMP shared hosting**, which is a hard requirement (WordPress/Moodle-style installation). It needs a long-running process manager, and the PHP-native document stack (mPDF with Arabic shaping and PDF/A-3, PhpWord, PhpSpreadsheet with formulas, horstoeko/zugferd) has no equally mature JavaScript equivalent for German e-invoicing.
- Rejected.

### Django (Python)
- Pros: mature ORM and admin, good i18n (gettext), strong PostgreSQL support.
- Cons: no shared-hosting deployment comparable to PHP; WSGI/ASGI process required. Team PHP know-how would be lost. E-invoicing libraries for ZUGFeRD exist (factur-x in Python) but the overall document stack would need to be rebuilt.
- Rejected.

### Livewire instead of Inertia + Vue
- Pros: less JavaScript, server-driven.
- Cons: the timesheet grid, Gantt (1.0), boards and offline PWA capture need rich client state and keyboard handling; Vue + TypeScript is the better fit. RTL and component libraries (Reka UI) are stronger in the Vue ecosystem.
- Rejected.

### TypeScript 7 now
- Rejected for 0.9 because vue-tsc cannot type-check on the native compiler yet. Re-evaluate when TypeScript 7.1 and Vue language tools v4 are stable (target: 1.1).

### Redis instead of Valkey as Docker default
- Both work (same protocol). Valkey is chosen as the bundled default because of its BSD license; customers may point to existing Redis.

## Consequences

**Positive**
- One codebase serves AMP, Docker, Kubernetes and SaaS; optional services improve performance but never change financial results.
- Mature, actively released packages for every critical area (most released in the last 30 days).
- PDF/A-3 with embedded XML can be produced in pure PHP with mPDF, so ZUGFeRD works on shared hosting (details and verification plan in ADR-0008).

**Negative and risks**
- **PHP 8.4 minimum** excludes hosts that only offer 8.3. Mitigation: installer explains the requirement; 8.4 has been released for two years and is common on cPanel/Plesk hosts.
- **PHP 8.6 adoption** is blocked until mPDF widens its PHP constraint (currently up to `~8.5.0`) and Pest supports it.
- **PhpWord maintenance:** last release 05.06.2025. Mitigation: we use only TemplateProcessor and bidi paragraph features; we keep a fork-ready wrapper and contract tests for DOCX output.
- **brick/money is pre-1.0:** pinned to the 0.15 minor; upgrades only with the money test suite green (ADR-0004).
- **horstoeko/zugferd has a pre-1.0 successor** (invoicesuite). Mitigation: own adapter interface (ADR-0008).
- **Database queue on AMP** means up to one-minute latency for emails and document generation; UI must show "queued" states honestly.
- **Inertia 3 and Vite 8** are recent majors; community examples may still target older majors.
- CI matrix cost (4 database combinations per pull request) is accepted for the multi-DB promise.

## Open questions

1. Product owner: confirm PHP 8.4 as minimum for the AMP ZIP (A-06 said 8.3 or newer).
2. Product owner: confirm MariaDB 10.11 as the supported floor (wider hosting reach vs. one more CI target).
3. Solution architecture: switch the build toolchain to Node 26 LTS for 1.0 after checking laravel-vite-plugin and Playwright support.
4. Solution architecture: re-check at scaffold the items marked "verify at scaffold" (Golos Text and Noto Naskh Arabic font sources).
