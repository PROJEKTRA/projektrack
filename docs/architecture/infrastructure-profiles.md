# Infrastructure profiles

Status: Phase 0 baseline, 16.11.2026. Owner: devops-engineer. Reviewed by: solution-architect, security-engineer.

Projektrack is one codebase and one release that runs in five operating profiles. The application detects which optional services exist and uses them; every optional service has a working fallback, so the plain AMP profile is always fully functional. Security controls (section "Mandatory HTTP headers" and `docs/security/security-baseline.md`) are identical in every profile and cannot be disabled by configuration.

| Profile | Who operates it | Typical size | Release artifact | Status in 0.9 |
|---|---|---|---|---|
| P1 AMP / shared hosting | Customer or hoster | 1 company, up to ~50 users | Release ZIP (with `vendor/` and built assets) | Ships, tested in CI |
| P2 Docker Compose | Customer or PROJEKTRA | 1 to a few companies, up to ~300 users on one host | Signed images on GHCR + `install.sh` | Ships, tested in CI |
| P3 Kubernetes (Helm) | Customer platform team | Larger, horizontally scaled | Signed images + Helm chart | Chart lint and template tests only; supported from 1.0 |
| P4 SaaS multi-tenant | PROJEKTRA | Many tenants per region | Same images, control plane | Not in 0.9 (self-signup from 1.0, US region 1.1) |
| P5 Managed dedicated (PROJEKTRA servers) | PROJEKTRA | 1 customer per instance | Same images as P2 | **0.9 delivery critical path** (decision C-03) |

Related decisions: C-03 (first customer gets a managed dedicated instance), A-06 (versions), A-07 (PROJEKTRA production on an EU VPS in Germany with Docker Compose), A-12 (0.9 load assumptions).

---

## 1. Common requirements (all profiles)

### 1.1 PHP

- PHP **8.3 or newer** (8.4 supported and tested). The exact minimum follows the Laravel major pinned in ADR-0001.
- Required extensions (the web installer and `projektrack doctor` block installation if one is missing):

| Extension | Why |
|---|---|
| `ctype`, `filter`, `hash`, `pcre`, `session`, `tokenizer`, `json` | Framework baseline |
| `mbstring`, `iconv` | Multibyte text, Arabic and Farsi strings |
| `intl` (ICU 72 or newer) | Plural rules, number, date and currency formats, Jalali and Hijri display |
| `openssl` | TLS client, signed URLs |
| `sodium` | Ed25519 license and release signature verification, application-level encryption |
| `pdo` plus `pdo_mysql` and/or `pdo_pgsql` | Database access |
| `bcmath` or `gmp` | Arbitrary precision arithmetic for money (`brick/money`) |
| `curl` | Outgoing HTTP (webhooks, license check-in, optional services) |
| `dom`, `xml`, `xmlwriter`, `xmlreader`, `simplexml`, `libxml` | E-invoicing (ZUGFeRD, XRechnung), DOCX, XLSX |
| `zip` | DOCX, XLSX, release and backup archives |
| `fileinfo` | Upload type detection by content |
| `gd` (with FreeType, JPEG, PNG, WebP) or `imagick` | Images in PDFs, thumbnails, logo processing |

- Recommended: `opcache` (performance), `apcu` (local cache on single-server AMP), `exif` (image orientation), `redis` (phpredis, when Redis is used).
- Recommended `php.ini` values: `memory_limit = 256M` (512M for large exports), `max_execution_time = 120` for web, unlimited for CLI, `upload_max_filesize = 32M`, `post_max_size = 40M`, `expose_php = Off`, `session.cookie_secure = 1`, `session.cookie_httponly = 1`, `session.use_strict_mode = 1`, `allow_url_include = Off`.
- Functions that must be available: `proc_open` is optional (used for `mysqldump`/`pg_dump` when present; otherwise the PHP-native dump is used).

### 1.2 Databases

| Engine | Supported | Notes |
|---|---|---|
| MySQL | 8.4 LTS | MySQL 8.0 is end of life and not supported. `utf8mb4`, `utf8mb4_0900_ai_ci`, InnoDB only. |
| MariaDB | 10.11 LTS, 11.4 LTS | `utf8mb4`, `utf8mb4_uca1400_ai_ci` on 11.4, InnoDB only. |
| PostgreSQL | 16, 17 | UTF8 encoding, ICU collation recommended. |

- Database-agnostic code; vendor-specific SQL only in the tested adapter layer (full-text search, JSON queries, advisory locks).
- Timestamps stored in UTC; money as integer minor units; durations as integer minutes.
- The database user needs DDL rights only during install and update. Where the profile allows two users (P2 to P5), the runtime user has no `DROP`/`ALTER` rights and no `UPDATE`/`DELETE` on the audit log table.

### 1.3 Cron and background work

One cron entry runs the scheduler and, when no queue workers exist, drains the database queue:

```
* * * * * /usr/bin/php /path/to/projektrack/projektrack cron:run >/dev/null 2>&1
```

- `cron:run` runs due scheduled tasks, then processes database queue jobs for up to 50 seconds, then exits. It holds a lock, so overlapping runs are impossible.
- A heartbeat is written on every run. If the last heartbeat is older than 3 minutes, admins see a banner and `projektrack doctor` reports a critical problem.
- Hosts without cron: an authenticated web cron endpoint is available (POST only, 256-bit secret in a header, rate limited, carries all no-index headers). This is a documented last resort and `doctor` reports it as a warning.

### 1.4 Mail

SMTP (with STARTTLS or implicit TLS) is the default; `sendmail` is the fallback on shared hosting. Emails never contain tracking pixels or rewritten links. SPF, DKIM and DMARC guidance is in `docs/admin/white-label.md`.

---

## 2. Service choices and fallbacks

| Concern | Preferred service | Fallback (always works) | Switch |
|---|---|---|---|
| Queue | Redis + Horizon workers | Database queue drained by `cron:run` | Automatic when Redis and a worker heartbeat are detected |
| Cache | Redis | APCu (single server) or file cache | Automatic |
| Sessions | Redis | Database sessions | Automatic |
| Locks | Redis | Database locks | Automatic |
| Search | Meilisearch | Database full-text (MySQL/MariaDB `FULLTEXT`, PostgreSQL `tsvector`) through the adapter layer | Automatic; reindex job runs on switch |
| Real-time | Reverb (WebSockets) | Smart polling: 15 s when the tab is visible, exponential backoff to 120 s, paused when hidden | Automatic |
| PDF | mPDF (default, also RTL and Arabic shaping) | mPDF is itself the fallback; Gotenberg is optional for pixel-perfect HTML/DOCX conversion | Per document template setting, falls back to mPDF if Gotenberg is down |
| Files | S3-compatible storage (MinIO or provider) | Local disk under `storage/app` (outside the web root) | Configured, verified by probe |
| Malware scan | ClamAV (clamd socket or TCP) | No scan; type, size and extension validation still enforced | Configured, verified by probe |
| Mail | SMTP | `sendmail` | Configured |

Rules:

- Fallbacks never weaken security: tenant scoping, permission checks, signed URLs and no-index headers are identical in every combination.
- The browser never talks directly to Meilisearch, MinIO or Gotenberg. All access goes through the application, which enforces tenant scope and permissions. Files are served by signed, expiring URLs through the application (or by `X-Accel-Redirect`/`X-Sendfile` after the application authorized the request).
- If an admin enables malware scanning and the scanner becomes unreachable, uploads fail closed: they are stored in quarantine and cannot be downloaded until a scan succeeds.

### 2.1 Capability detection

Detection runs at boot of the admin system screen, on `projektrack doctor`, and as a scheduled probe every 10 minutes. Results are cached and shown in Admin > System > Capabilities with plain-language explanations.

| Capability | Detected how | Fallback when missing | Severity in `doctor` |
|---|---|---|---|
| PHP version and extensions | `PHP_VERSION`, `extension_loaded()` | None: installer blocks | Critical |
| Database engine and version | `SELECT VERSION()` / `SHOW server_version`, compared to the support table | None: installer blocks | Critical |
| Writable folders | Write, read and delete a probe file in `storage/`, `bootstrap/cache/` | None: installer blocks | Critical |
| Web root points to `public/` | Request `/.env` and `/storage/` from outside: both must return 404 | None | Critical |
| HTTPS | Request scheme, `X-Forwarded-Proto` from trusted proxies only, HSTS present | Installer warns; production requires TLS | Critical |
| Cron | Scheduler heartbeat younger than 3 minutes | Web cron endpoint | Critical if no heartbeat |
| Redis | `REDIS_HOST` set, extension or client present, `PING` answered within 500 ms | Database queue, file or APCu cache, database sessions | Info |
| Queue workers | Worker heartbeat key younger than 60 s | Database queue drained by `cron:run` | Info (warning if Redis exists but no worker runs) |
| Meilisearch | `MEILISEARCH_HOST` set, `GET /health` returns `available`, version within supported range | Database full-text | Info |
| Reverb | `BROADCAST_CONNECTION=reverb`, server reachable, test broadcast received | Smart polling | Info |
| Gotenberg | `GOTENBERG_URL` set, `GET /health` returns `up` | mPDF | Info |
| S3 storage | Disk configured, write, read and delete of a random probe object succeed | Local disk | Warning if configured but failing |
| ClamAV | `PING` returns `PONG`, EICAR test string detected | No scan (or quarantine when scanning is enabled) | Warning if enabled but failing |
| SMTP | Connection and `EHLO`, optional test mail from admin UI | `sendmail` or log-only with admin banner | Warning |
| OPcache | `opcache_get_status()` | Runs slower | Info |
| `proc_open` and dump binaries | Function enabled and `mysqldump`/`pg_dump` found | PHP-native database dump | Info |
| Outgoing HTTP | Optional; only to the license service when activated | Offline license file | Info |
| Server signature | External self-request: `Server` header empty or generic, no `X-Powered-By` | None | Warning (shared hosts may add `Server: Apache` that only the hoster can remove) |
| No-index headers | External self-request to a page, an asset, a download, a 404 and `robots.txt` | None | Critical |

---

## 3. Profile P1: AMP / shared hosting

**Requirements**: Apache 2.4 with `mod_rewrite` and `mod_headers`, or Nginx 1.24+ with PHP-FPM; PHP and extensions per 1.1; MySQL 8.4, MariaDB 10.11/11.4 or PostgreSQL 16+; one cron entry; ability to point the document root to `public/` (cPanel and Plesk both support this). No Node, Composer, Redis or Docker on the server.

**Components**: release ZIP (`vendor/` and compiled assets included), web installer, `.htaccess` in `public/` and in the project root (deny-all safety net if the web root is misconfigured), `nginx.conf.example`.

**Service choice**: database queue plus cron, APCu or file cache, database sessions, database full-text search, smart polling, mPDF, local disk (S3 optional if the hoster allows outgoing connections), ClamAV usually not available.

**TLS and domains**: the hoster's TLS (usually ACME-based). Custom domain chosen by the customer. Documentation warns that every publicly trusted certificate for a host name is published in Certificate Transparency (CT) logs, so a discreet, unobvious subdomain (for example `k7m2q9x4.customer.example`) or an existing wildcard certificate is recommended. HSTS is set by `.htaccess`; HSTS preload is never used because the preload list is public.

**Backups and restore**:
- `projektrack backup` (CLI) and Admin > Backups (web, runs in chunks so it fits PHP time limits): database dump (native binary if available, PHP-native otherwise) plus files, packed into one archive, encrypted with libsodium (XChaCha20-Poly1305) using a backup key that is shown once at install and stored outside the database.
- Default target: `storage/backups/` (outside the web root), retention 7 daily, 4 weekly. Optional: copy to S3-compatible storage.
- Restore: `projektrack restore <archive>` or Admin > Backups > Restore on a fresh installation; the restore verifies archive signature, schema version and checksum before changing anything.
- The hoster's own backups are recommended in addition.

**Updates**: one-click update in Admin > Updates. Sequence: pre-update check (PHP and DB versions, disk space at least 2.5x release size plus database size, writable folders, module compatibility, pending migrations, license validity) -> download -> verify Ed25519 signature and SHA-256 checksum -> automatic backup -> maintenance mode with branded page -> extract into a staging folder and swap -> migrations (expand/contract only) -> cache rebuild -> health check -> on any failure automatic rollback to the previous code folder and the pre-update backup. Steps run in chunks via AJAX so they fit shared hosting time limits. Target: a minor update in under 5 minutes without SSH.

**Scaling limits**: one server; designed for about 50 concurrent users and fewer than 10 tenants (A-12). Background jobs start within 60 seconds (cron granularity). Large exports and full reindexing run as chunked jobs. Real-time updates are polling-based. No high availability.

**Tested in CI**: Apache + PHP-FPM container without Redis or any optional service; release ZIP built exactly as shipped; headless web installer run (Playwright); full Pest suite with `QUEUE_CONNECTION=database`; cron simulation; one-click update from the previous release including a forced failure that must roll back; crawl test; matrix MySQL 8.4, MariaDB 10.11 and 11.4, PostgreSQL 16 and 17; PHP 8.4 and 8.5. Nginx sample config tested in the same job with a second web server container.

---

## 4. Profile P2: Docker Compose

**Requirements**: Linux host (Ubuntu 24.04 LTS reference, A-01), Docker Engine 27+ with Compose v2, 2 vCPU, 4 GB RAM minimum (4 vCPU, 8 GB recommended with Meilisearch and Gotenberg), 40 GB disk, ports 80 and 443 reachable, a DNS name.

**Installation**: `curl -fsSL https://get.projektrack.example/install.sh | bash`. The script checks prerequisites, asks for domain and admin email, generates secrets (`APP_KEY`, database passwords, backup key, encryption key file) with a CSPRNG into files with mode 0600, verifies image signatures with cosign, and starts the stack. The admin account is completed through a one-time setup link (the admin sets their own password and 2FA).

**Components** (one Compose project, one internal network; only the proxy publishes ports):

| Service | Image | Notes |
|---|---|---|
| `proxy` | Caddy | Automatic TLS (ACME), mandatory headers, removes `Server` |
| `app` | `ghcr.io/<org>/projektrack` | Nginx + PHP-FPM, read-only root filesystem, non-root user |
| `worker` | same image | Horizon queue workers |
| `scheduler` | same image | `cron:run` loop |
| `reverb` | same image | WebSockets, proxied under the same origin |
| `db` | MariaDB 11.4 LTS (default) or PostgreSQL 17 | Data volume, not exposed |
| `redis` | Redis 7 (or a compatible fork) | Password, no persistence of sessions required |
| `search` | Meilisearch | Master key, internal only |
| `pdf` | Gotenberg | Internal only, no egress, Chromium blocked from fetching network URLs |
| `files` | MinIO | Internal only, buckets private |
| `clamav` | ClamAV | Optional Compose profile `scan` |
| `backup` | same image | Scheduled encrypted backups to local volume and off-site target |

**Service choice**: all preferred services; fallbacks remain active if a container is stopped.

**TLS and domains**: Caddy obtains certificates via ACME. CT log note as in P1. Option: DNS-01 wildcard certificate if the operator wants to hide the host name from CT logs.

**Backups and restore**: `backup` service runs database dump every 6 hours and files daily, encrypted with the backup key, retention 14 daily and 8 weekly, copied off-site to S3-compatible storage in the same jurisdiction. Restore with `projektrack restore` into a fresh stack. Restore is rehearsed by CI (see below).

**Updates**: Admin > Updates (one click) or `projektrack update`: pre-update check -> pull new image by digest -> verify cosign signature -> backup -> maintenance mode -> migrations -> swap containers -> health check -> automatic rollback to the previous image digest and backup on failure.

**Scaling limits**: single host, vertical scaling. Indicative capacity up to ~300 users and the performance budget dataset (100,000 time entries and 50,000 tickets per tenant); verified by performance tests from 1.0 (A-12). No high availability; host failure means restore from backup.

**Tested in CI**: images built and signed; `docker compose up` from the release images; install script in a fresh VM (nightly); E2E smoke suite; update from the previous release and forced rollback; backup and restore round trip with data checksum comparison; crawl test against the proxy; image scan and SBOM.

---

## 5. Profile P3: Kubernetes (Helm)

**Requirements**: Kubernetes 1.30+, an ingress controller or Gateway API implementation, cert-manager for TLS, external database (managed MySQL/MariaDB/PostgreSQL per 1.2), S3-compatible object storage, Redis, optional Meilisearch and Gotenberg (in-cluster subcharts or external).

**Components**: Deployments `web` (Nginx + PHP-FPM in the pod, so mandatory headers do not depend on the ingress), `worker`, `reverb`; CronJob or Deployment `scheduler`; Job `migrate` (Helm pre-upgrade hook); HorizontalPodAutoscaler for `web` and `worker`; PodDisruptionBudgets; NetworkPolicies (default deny, only required flows); Secrets referenced from an external secret store where available.

**Service choice**: Redis, S3, Meilisearch and Reverb are expected; local disk is not allowed (the chart refuses to start with `FILESYSTEM_DISK=local` when replicas > 1).

**TLS and domains**: cert-manager issues certificates; wildcard certificate via DNS-01 recommended for discretion. Headers are set inside the pod; the chart does not depend on ingress snippet annotations (often disabled for security reasons). Operators should configure their ingress controller not to emit version information.

**Backups and restore**: database backups are the operator's responsibility (managed database snapshots or PITR); the chart provides a `backup` CronJob for logical dumps and object storage sync, encrypted with the backup key. Restore documented in `docs/admin/backup.md`.

**Updates**: `helm upgrade` with rolling deployment and zero downtime; migrations run in a pre-upgrade Job and follow expand/contract so old and new pods can run side by side; `helm rollback` restores the previous release (data rollback only from backup, which is why migrations are never destructive in the same release).

**Scaling limits**: web and workers scale horizontally; Reverb scales horizontally with Redis pub/sub; the database is the limiting component (vertical scaling, read replicas planned post-1.0).

**Tested in CI**: 0.9: `helm lint`, `helm template` with schema validation (kubeconform), policy checks (non-root, read-only root filesystem, resource limits). From 1.0: install and upgrade on an ephemeral kind cluster with smoke tests and crawl test.

---

## 6. Profile P4: SaaS multi-tenant (PROJEKTRA)

Not part of 0.9. Designed now so the data model needs no breaking change.

**Requirements**: Kubernetes (P3) or Docker hosts per region, operated by PROJEKTRA; EU region (default) and US region (from 1.1). Tenant data, backups and logs never leave their region.

**Components**: P3 components plus the control plane (provision tenants, assign domains, rollouts in waves, health, backup verification, licensing), per-region object storage and database clusters, central PROJEKTRA-operated monitoring (no third-party monitoring service that would learn tenant URLs).

**Tenancy**: shared database with central tenant global scope by default; database-per-tenant for Enterprise. Cross-tenant tests in CI (see security baseline).

**TLS and domains**:
- Tenants live under `*.projektrack-cloud.example` with **wildcard DNS and a wildcard certificate** (DNS-01), so tenant names never appear in CT logs or DNS zone data. A wildcard covers exactly one label, so tenant hosts are always `<label>.projektrack-cloud.example`.
- Tenant labels are random by default (8 characters from a 32-character alphabet, for example `k7m2q9x4`), with an optional readable alias chosen by the customer.
- Custom domains: automatic certificates per domain (HTTP-01 or TLS-ALPN-01) after DNS verification. The admin UI warns that a dedicated certificate for a custom domain is published in CT logs and recommends an unobvious subdomain or bringing a wildcard certificate.
- Unknown host names receive a generic 404 with no-index headers and no product fingerprint.

**Backups and restore**: continuous database backups with point-in-time recovery (binary log or WAL archiving), 35-day retention, object storage versioning, per-tenant logical export for tenant-level restore, restore drills monthly.

**Updates**: rollouts in waves through the control plane (internal, Preview, 10 %, 50 %, 100 %), automatic halt on error rate or health regression; customers see "What's new" afterwards.

**Scaling limits**: horizontal for web, workers and Reverb; database clusters sharded by region and, for large tenants, dedicated database.

**Tested in CI**: full multi-tenant suite with at least three tenants, cross-tenant access attempts on every route and API endpoint, host routing tests (random label, alias, custom domain, unknown host), crawl test across tenants.

---

## 7. Profile P5: Managed dedicated instance on PROJEKTRA servers

The delivery path for the first customer license on 1 December 2026 (C-03).

**Hosting**: PROJEKTRA-operated VPS in an EU data center in Germany (A-07), Ubuntu 24.04 LTS, Docker Compose (P2 stack). Default isolation: **one VPS per managed customer** (own network, database, volumes, secrets, backup key). Alternative for small customers: several Compose projects on one host with separate networks, database servers and secrets. The choice for the first customer is an open decision for the product owner; the recommendation is one VPS per customer.

**Host hardening**: SSH keys only, no password or root login, firewall allowing 80/443 publicly and SSH only from PROJEKTRA admin addresses or a VPN, unattended security upgrades, Docker rootless or user namespace remapping, audit logging of admin access, disk encryption where the provider supports it, time synchronization.

**Domains and TLS**: the customer's domain or a random label under the PROJEKTRA wildcard zone. Recommendation to the customer: either an unobvious subdomain of their own domain (CT log note) or a random label under `*.projektrack-cloud.example` (covered by the wildcard certificate, not visible in CT logs). If the customer's own domain is used, the customer creates a CNAME; Caddy obtains the certificate.

**Monitoring**: health endpoint (returns only `OK`, IP-restricted to the PROJEKTRA monitoring host), self-hosted uptime and resource monitoring operated by PROJEKTRA, disk and backup age alerts, certificate expiry alerts, log retention 90 days on the host.

**Backups**: as P2, plus off-site copy to a second EU location operated or contracted by PROJEKTRA, monthly restore drill on a scratch VPS. Targets for 0.9: RPO 6 hours, RTO 4 hours (business hours).

**Updates**: PROJEKTRA applies patch releases in an agreed maintenance window and minor releases after customer notice; pre-update backup and automatic rollback as P2.

**Tested in CI**: as P2; additionally the delivery runbook is rehearsed on a staging VPS before go-live (basic auth in front of staging, per the invisibility rules).

### 7.1 Release 0.9 delivery runbook (outline)

| When | Step | Done when |
|---|---|---|
| By Fri 21 Nov | Provision VPS (EU, Germany), harden host, install Docker, create DNS records, provision staging VPS behind basic auth | `doctor` green on staging, SSH only by key |
| By Fri 21 Nov | Agree domain strategy with customer (own subdomain or random wildcard label), document CT log implications | Domain decision recorded |
| Sat 22 to Sat 28 Nov | Deploy nightly release candidates to staging, run E2E smoke, backup and restore drill, crawl test | Restore drill restores and data checksums match |
| Sun 29 Nov | Install release candidate on production VPS with demo-free empty tenant, verify headers, `robots.txt`, server signature, TLS rating, CSP | `projektrack doctor` external check green |
| Mon 30 Nov 16:00 | Go/no-go: security review signed off, all CI gates green, backups verified, rollback rehearsed | Go decision recorded |
| Tue 1 Dec | Install signed 0.9.0 (verify cosign signature and image digest), run migrations, create tenant with customer settings (country pack DE, language, currency) | Health check green |
| Tue 1 Dec | Issue license (Founding Customer plan) from the PROJEKTRA instance, activate with activation code | License screen shows edition and seats |
| Tue 1 Dec | Send one-time admin setup link to the customer admin; customer sets own password and 2FA (PROJEKTRA never knows the password) | Admin logged in with 2FA |
| Tue 1 Dec | First backup taken and verified off-site; monitoring and alerts active | Backup age < 6 h, alert test received |
| Tue 1 Dec | Onboarding session: workspace wizard, invitations, first project, first time entry | "Getting started" checklist completed |
| D+1, D+7 | Review logs, backup restore spot check, performance, open issues | Review notes filed |

Rollback plan for delivery day: if 0.9.0 fails health checks, restore the release candidate snapshot taken on 29 Nov and postpone activation by hours, not days; customer communication template prepared in advance.

---

## 8. Mandatory HTTP headers and reference web server configurations

The application sets all headers on every response it generates (middleware, also on redirects, errors, API responses and downloads). The web server configs below set the same headers for files the web server serves directly (assets, `robots.txt`), and remove server fingerprints. Every response must carry:

| Header | Value |
|---|---|
| `X-Robots-Tag` | `noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate` |
| `Referrer-Policy` | `no-referrer` |
| `Strict-Transport-Security` | `max-age=31536000` (add `includeSubDomains` only when the operator controls all subdomains; never `preload`) |
| `Content-Security-Policy` | HTML from the app: nonce-based policy (below); static files: `default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'` |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` (legacy, in addition to `frame-ancestors 'none'`) |
| `Permissions-Policy` | `camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()` |
| `Cross-Origin-Opener-Policy` | `same-origin` |
| `Cross-Origin-Resource-Policy` | `same-origin` |
| `Server`, `X-Powered-By` | removed (or generic where the platform cannot remove them) |

Application CSP for HTML (nonce generated per response, 128 bits):

```
default-src 'self'; script-src 'nonce-{nonce}' 'strict-dynamic'; style-src 'self' 'nonce-{nonce}';
img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; frame-src 'none'; frame-ancestors 'none';
object-src 'none'; base-uri 'none'; form-action 'self'; upgrade-insecure-requests
```

When Reverb is active, `connect-src` adds the same-origin `wss:` URL. No third-party host ever appears in the policy.

### 8.1 Apache (`public/.htaccess`)

```apache
# Projektrack: mandatory headers and hardening for files served by Apache.
Options -Indexes -MultiViews
ServerSignature Off

<IfModule mod_headers.c>
    Header always set X-Robots-Tag "noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate"
    Header always set Referrer-Policy "no-referrer"
    Header always set Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == 'on'"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-Frame-Options "DENY"
    Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()"
    Header always set Cross-Origin-Opener-Policy "same-origin"
    Header always set Cross-Origin-Resource-Policy "same-origin"
    Header always unset X-Powered-By
    Header unset X-Powered-By

    # Static files get a strict CSP. PHP responses carry their own nonce-based CSP.
    <FilesMatch "\.(css|js|mjs|map|woff2?|png|jpe?g|gif|webp|svg|ico|txt|json|webmanifest)$">
        Header always set Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"
    </FilesMatch>
</IfModule>

# Never serve dotfiles or anything that is not meant to be public.
<FilesMatch "^\.">
    Require all denied
</FilesMatch>

<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTPS} !=on
    RewriteCond %{HTTP:X-Forwarded-Proto} !=https
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

Notes: `ServerTokens Prod` can only be set in the server configuration; on shared hosting the `Server: Apache` header may remain and `doctor` reports it as a warning. The upload storage lives outside `public/`; a root-level `.htaccess` with `Require all denied` protects the installation if the document root was set to the project root by mistake.

### 8.2 Nginx (`deploy/nginx.conf.example`)

```nginx
# Requires: server_tokens off in the http block.
# Optional: headers-more module to remove the Server header entirely (more_clear_headers Server;).

# /etc/nginx/snippets/projektrack-headers.conf
add_header X-Robots-Tag "noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate" always;
add_header Referrer-Policy "no-referrer" always;
add_header Strict-Transport-Security "max-age=31536000" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-Frame-Options "DENY" always;
add_header Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()" always;
add_header Cross-Origin-Opener-Policy "same-origin" always;
add_header Cross-Origin-Resource-Policy "same-origin" always;

server {
    listen 80;
    server_name app.projektrack.example;
    include snippets/projektrack-headers.conf;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name app.projektrack.example;
    root /var/www/projektrack/public;
    index index.php;

    ssl_certificate     /etc/ssl/projektrack/fullchain.pem;
    ssl_certificate_key /etc/ssl/projektrack/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;

    server_tokens off;
    client_max_body_size 40m;

    # add_header in a location replaces inherited ones, so every location includes the snippet.
    include snippets/projektrack-headers.conf;

    location ~ /\. { deny all; include snippets/projektrack-headers.conf; }

    location ~* \.(css|js|mjs|map|woff2?|png|jpe?g|gif|webp|svg|ico|txt|json|webmanifest)$ {
        include snippets/projektrack-headers.conf;
        add_header Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'" always;
        expires 30d;
        try_files $uri =404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ ^/index\.php(/|$) {
        include snippets/projektrack-headers.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_split_path_info ^(.+\.php)(/.*)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_hide_header X-Powered-By;
        internal;
    }

    # No other PHP file is ever executed.
    location ~ \.php$ { return 404; }

    # Protected file delivery after the application authorized the request.
    location /_protected/ {
        internal;
        alias /var/www/projektrack/storage/app/;
        include snippets/projektrack-headers.conf;
        add_header Content-Security-Policy "default-src 'none'; sandbox" always;
    }
}
```

### 8.3 Caddy (`deploy/Caddyfile`)

```caddy
{
    servers {
        protocols h1 h2 h3
    }
}

app.projektrack.example {
    encode zstd gzip

    header {
        X-Robots-Tag "noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate"
        Referrer-Policy "no-referrer"
        Strict-Transport-Security "max-age=31536000"
        X-Content-Type-Options "nosniff"
        X-Frame-Options "DENY"
        Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=(), usb=(), interest-cohort=()"
        Cross-Origin-Opener-Policy "same-origin"
        Cross-Origin-Resource-Policy "same-origin"
        -Server
        -X-Powered-By
        defer
    }

    @static path *.css *.js *.mjs *.map *.woff *.woff2 *.png *.jpg *.jpeg *.gif *.webp *.svg *.ico *.webmanifest
    header @static Content-Security-Policy "default-src 'none'; img-src 'self'; style-src 'self'; font-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'"

    reverse_proxy app:8080
}
```

`defer` applies header changes after the upstream responded, so `-Server` and `-X-Powered-By` also remove headers set by upstream components.

### 8.4 Kubernetes

The `web` pod runs the Nginx config of 8.2, so the headers do not depend on the ingress controller. The chart documents how to disable version headers on common ingress controllers and validates the result with the crawl test.

---

## 9. CI coverage summary

| Check | P1 AMP | P2 Docker | P3 Helm | P4 SaaS | P5 Managed |
|---|---|---|---|---|---|
| Full test suite on MySQL 8.4, MariaDB 10.11/11.4, PostgreSQL 16/17 | Yes | Yes (same suite) | Shared | Shared | Shared |
| Runs without Redis, Meilisearch, Reverb, Gotenberg, S3 | Yes | Fallback test per service | No | No | No |
| Release artifact installed as shipped | ZIP + web installer | Images + install script (nightly VM) | `helm template` (install from 1.0) | From 1.0 | Staging rehearsal |
| Update from previous release, forced failure and rollback | Yes | Yes | From 1.0 | From 1.0 | Via P2 |
| Backup and restore round trip | Yes | Yes | From 1.0 | From 1.0 | Via P2 plus monthly drill |
| Crawl test (no-index, robots, fingerprint, third-party requests) | Apache and Nginx | Caddy | From 1.0 | Multi-host | Pre go-live |
| Signature verification of artifact | ZIP signature | cosign | cosign | cosign | cosign |
