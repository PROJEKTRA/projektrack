# E03 Installation, release and updates

Epic goal: signed releases that install and update safely. Per decision C-03, the first customer is delivered as a managed dedicated instance on PROJEKTRA servers, so the Docker Compose production stack and the update path are delivery critical. The AMP web installer remains Must for 0.9 but is not on the delivery critical path.

Estimates include implementation, tests, review and docs (build / tests / review and docs).

| ID | Title | Priority | Estimate | Sprint |
|---|---|---|---|---|
| S-0923 | Docker Compose production stack (managed instance profile) | Must | 14 | L1 |
| S-0924 | Signed release pipeline (ZIP, images, checksums, SBOM) | Must | 12 | L1 |
| S-0925 | AMP release ZIP with web installer and fallbacks | Must (not delivery critical) | 22 | L1 |
| S-0926 | Docker one-command installer script | Must (not delivery critical) | 8 | L1 |
| S-0927 | Operations CLI: install, update, backup, restore | Must | 14 | L2 |
| S-0928 | One-click update with pre-update check, backup and rollback | Must | 26 | L3 |
| S-0929 | Fresh-server install tests, upgrade test and release candidate | Must | 14 | Hardening |

Epic total: Must 110 h.

---

### S-0923 Docker Compose production stack (managed instance profile)

| Field | Value |
|---|---|
| Persona | Instance operator (PROJEKTRA operations) |
| Priority | Must (delivery critical) |
| Estimate | 14 h (build 9, tests 3, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | Dev server available (A-01) |
| Modules | deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As an instance operator, I want a production Docker Compose stack with TLS, workers, scheduler and backups, so that I can run the PROJEKTRA instance and dedicated customer instances reliably.

```gherkin
Scenario: Stack starts with secure defaults
  Given a fresh Ubuntu 24.04 VPS with Docker
  When the operator starts the stack with a domain and admin email
  Then app, worker, scheduler, database, Redis and a reverse proxy with automatic TLS are running
  And secrets are generated, not taken from defaults
  And the health endpoint returns only "OK"

Scenario: Backups
  Then a nightly database and file backup is written to a configured target
  And a documented restore drill restores it on a second host
```

Implementation note (23.11.2026): `deploy/docker/` holds the stack.
- **Services:** application image (Nginx and PHP-FPM as `www-data`, read-only root file system, no capabilities), Caddy with automatic TLS as the only published service, queue worker, scheduler, a one-shot `migrate` service, MariaDB 11.4 and Valkey 8.
- **Secrets:** `setup.sh` generates every secret once into `.env` (mode 0600). The database has a migration account and a runtime account that changes rows but no schema and only adds to the audit log; the root password is random.
- **Tenant setup:** `projektrack:tenant:create` creates the tenant, its address and the first administrator, who receives a setup link by mail.
- **Backups:** every 6 hours, encrypted with age to a public key whose private key stays off the host, signed with an HMAC key, kept 14 days on the host (off-site copy by the operator until BL-017). `projektrack-restore` checks signature and checksums, decrypts once as a trial and restores into an empty instance only.
- **Networks:** the proxy and the database are on separate networks; the database network has no internet access. No log holds request paths.
- **CI:** builds and starts the stack, checks secrets, headers, health, the worker and the database rights, and runs the restore drill into a second stack. Guide: `docs/admin/docker.md`.
- **Not in this story:** Meilisearch, Gotenberg, MinIO, ClamAV and Reverb (fallbacks active), PostgreSQL as stack database, Horizon (plain queue worker), signed images (S-0924) and the update command (S-0927).

---

### S-0924 Signed release pipeline (ZIP, images, checksums, SBOM)

| Field | Value |
|---|---|
| Persona | Instance operator |
| Priority | Must |
| Estimate | 12 h (build 7, tests 2, review and docs 3) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0908 |
| Modules | deploy, CI |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As an instance operator, I want every release signed and accompanied by checksums and an SBOM, so that I can verify that what I install is exactly what PROJEKTRA built.

```gherkin
Scenario: Release artifacts
  When a tag v0.9.0 is pushed
  Then CI builds a release ZIP containing vendor/ and compiled assets, Docker images on GHCR, SHA-256 checksums and a CycloneDX SBOM
  And images are signed with cosign and the ZIP checksum file is signed

Scenario: Verification
  Given a ZIP whose content was modified after signing
  Then the update and install verification rejects it
```

Implementation note (23.11.2026): `.github/workflows/release.yml` runs on a tag `vX.Y.Z` of a commit whose CI on `main` is green.
- **ZIP:** built from the committed files only (`deploy/release/build-zip.sh`), with `vendor/` and compiled assets. Comes with a CycloneDX SBOM of Composer and npm.
- **Image:** pushed by digest only, and exactly that digest is scanned (fails on fixable critical findings). It carries provenance and a CycloneDX SBOM.
- **Approval:** nothing is signed or tagged before a reviewer approves the `release` environment. The approved job signs `SHA256SUMS` with the Ed25519 release key and by cosign, and signs and attests the image; it runs no third-party package code. A job without write rights then verifies with the keys of this and the previous release before the image is tagged and the release published.
- **What is signed:** `SHA256SUMS` only. It covers the ZIP and the other files, which is how baseline 9 is met.
- **Verification:** `projektrack:release:verify --release=X.Y.Z` checks the signature against the keys shipped in `resources/release/public-keys.txt`, the checksum and the version (name and `VERSION` inside), and fails closed. There is no environment variable or setting for the trusted keys. A new installation is verified with OpenSSL and the key from the repository, without running code from the ZIP.
- **For S-0927 and S-0928:** the updater uses this check and must also refuse a version that is not newer than the running one. The CI job "Release ZIP and signature (dry run)" builds the ZIP, signs it with a throwaway key and checks that the verification accepts it and refuses a changed ZIP or checksum list.
- **Version:** set by the build in `VERSION`.
- **Guide:** `docs/admin/releases.md`.
- **Offline approval (decision of 23.11.2026):** GitHub cannot enforce an approval on this repository's plan, so no key exists there. `deploy/release/approve.sh` signs the files of the build on a maintainer's machine, for a commit id from the maintainer's own review (`tests/release/approve.sh` tests it). The image is covered by its digest in the signed `SHA256SUMS`; cosign is no longer used, which differs from the criterion above. The notes above on the `release` environment, cosign, attestation and tagging no longer apply.
- **Open, for the product owner:** generate the release key and a standby key and commit their public keys. Until then no release can be approved.
- **Not in this story:** base images pinned by digest and `npm ci --ignore-scripts` (BL-018).

---

### S-0925 AMP release ZIP with web installer and fallbacks

| Field | Value |
|---|---|
| Persona | Self-hosting admin on shared hosting |
| Priority | Must (not delivery critical per C-03; cut candidate) |
| Estimate | 22 h (build 12, tests 6, review and docs 4) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0924, S-0916 |
| Modules | core, deploy |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a self-hosting admin, I want to upload a ZIP and finish installation in the browser, so that I can run the product on plain AMP hosting like WordPress or Moodle.

```gherkin
Scenario: Installation on shared hosting
  Given a fresh AMP hosting account with PHP, MySQL and cron only
  When the release ZIP is uploaded and the web installer is completed
  Then Projektrack runs in Bosnian with all Community modules
  And a service report PDF can be generated without Docker, Node or Redis

Scenario: Requirements check
  Given the PHP extension "intl" is missing
  Then the installer explains the problem in plain language and blocks the next step

Scenario: Installer locks itself
  Given installation is complete
  When anyone opens the installer URL again
  Then it returns 404

Scenario: Progressive fallbacks
  Then without Redis the database queue and file cache are used, and one cron entry runs all background jobs
  And Apache .htaccess and an Nginx sample config are shipped
```

Implementation note (23.11.2026): the command line part only. `projektrack:install` checks the requirements in plain language, writes `.env`, migrates and creates the tenant; one cron entry also drains the database queue; the root `.htaccess` refuses everything if the web root is set wrongly. CI (`tests/amp/install.sh`) installs the ZIP on Apache with MariaDB and nothing else. Not built: the web installer, its lock, PDF generation, the Nginx sample. Guide: `docs/admin/plain-hosting.md`.

---

### S-0926 Docker one-command installer script

| Field | Value |
|---|---|
| Persona | Self-hosting admin |
| Priority | Must (not delivery critical; cut candidate) |
| Estimate | 8 h (build 4, tests 2, review and docs 2) |
| Sprint | L1 Foundation (18-21 Nov) |
| Dependencies | S-0923 |
| Modules | deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As a self-hosting admin, I want a single command that installs Projektrack with Docker, so that I am running in minutes.

```gherkin
Scenario: One command
  Given a server with Docker
  When the admin runs the install script
  Then it checks prerequisites, asks for domain and admin email, generates secrets and starts the stack
  And it verifies image signatures before starting
```

---

### S-0927 Operations CLI: install, update, backup, restore

| Field | Value |
|---|---|
| Persona | Instance operator |
| Priority | Must |
| Estimate | 14 h (build 8, tests 4, review and docs 2) |
| Sprint | L2 Core (22-25 Nov) |
| Dependencies | S-0923, S-0924 |
| Modules | core, deploy |
| Edition | Community |
| Reviewers | security, qa |

**Story:** As an instance operator, I want CLI commands for install, update, backup and restore, so that I can operate managed instances in a scripted, repeatable way.

```gherkin
Scenario: Backup and restore
  When the operator runs "projektrack backup"
  Then a consistent archive of database and files with a checksum is written
  And "projektrack restore <archive>" restores it on an empty instance

Scenario: CLI update with rollback
  Given a newer signed release
  When the operator runs "projektrack update"
  Then a backup is taken, maintenance mode is enabled, migrations and cache rebuild run, and a health check is done
  And if any step fails the backup is restored automatically and the previous version runs again
```

Implementation note (23.11.2026): `deploy/docker/projektrack` (install, backup, restore, update) for Docker instances, run on the host. An update accepts only a signed release whose image is newer than the running version. The automatic rollback uses a local snapshot instead of the encrypted backup, whose private key is off the host. CI (`tests/docker/update.sh`) updates, checks refusals, and forces a failing migration and a failing health check; both must end with the same data and the previous version. Plain hosting (AMP) gets its update and backup with S-0925 and S-0928. Guide: `docs/admin/docker.md`.

---

### S-0928 One-click update with pre-update check, backup and rollback

| Field | Value |
|---|---|
| Persona | Tenant admin of a self-hosted instance |
| Priority | Must (cut candidate: managed instances can use S-0927) |
| Estimate | 26 h (build 14, tests 8, review and docs 4) |
| Sprint | L3 Money and onboarding (26-28 Nov) |
| Dependencies | S-0927 |
| Modules | core |
| Edition | Community |
| Reviewers | security, ux, qa |

**Story:** As a self-hosted admin, I want to update with one click after a clear pre-update check, so that I can move from 0.9 to 0.10 without help.

```gherkin
Scenario: Update available
  Given version 0.10.0 is published on the stable channel
  Then Admin > Updates shows "Version 0.10.0 available" with translated release notes

Scenario: Pre-update check
  When the admin opens the update
  Then PHP and database versions, disk space, writable folders, module compatibility, pending migrations and license validity are checked
  And problems are explained in plain language before anything changes

Scenario: Safe update with rollback
  When the admin clicks "Update now"
  Then a backup is taken, the signed release is verified, a branded maintenance page is shown, migrations run and a health check passes
  And if any step fails the system rolls back to the backup and reports the failed step

Scenario: Update history
  Then Admin > Updates lists version, date, user, duration and result of every update
```

---

### S-0929 Fresh-server install tests, upgrade test and release candidate

| Field | Value |
|---|---|
| Persona | Instance operator |
| Priority | Must |
| Estimate | 14 h (test runs 8, fixes 4, docs 2) |
| Sprint | Hardening (29-30 Nov) |
| Dependencies | S-0923 to S-0928 |
| Modules | deploy |
| Edition | Community |
| Reviewers | qa, security |

**Story:** As an instance operator, I want the release candidate installed and upgraded on fresh servers before go/no-go, so that the first customer gets a proven build.

```gherkin
Scenario: Fresh install matrix
  Then the release candidate is installed on a fresh Docker host and on a fresh AMP account
  And the end-to-end smoke suite (sign in, book time, service report, invoice, license screen) passes on both

Scenario: Upgrade path
  Given an instance on the previous release candidate with demo data
  When it is updated to the final candidate
  Then no data is lost and the smoke suite passes

Scenario: Go/no-go
  Then a go/no-go record is written on 30 Nov at 16:00 listing open defects, review findings and cut scope
```
