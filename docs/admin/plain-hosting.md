# Installing Projektrack on plain hosting

For operators of shared or plain AMP hosting (S-0925): Apache, PHP, MySQL or MariaDB and one cron entry. No Docker, Redis, Composer or Node on the server. CI installs the same ZIP this way on every change (`tests/amp/install.sh`).

## Requirements

- PHP 8.4 or newer, for the web and on the command line, with the extensions bcmath, ctype, curl, dom, fileinfo, gd, intl, mbstring, openssl, pdo, pdo_mysql, session, sodium, tokenizer, xml, xmlreader, xmlwriter and zip. The installation names a missing one.
- MySQL 8.4 or MariaDB 11.4 with an empty database and an account that may create tables in it.
- Apache with `mod_rewrite` and `AllowOverride`. The domain must point to a subfolder (`public/`).
- Shell access (SSH) and a cron entry every minute. A web installer does not exist yet.
- A host name of its own (not a subfolder of a site) with a certificate. Turn on "force HTTPS" in the hosting panel and check that `http://` redirects.
- A mail server account. The first administrator gets the setup link by mail.

## Get the ZIP

Until the first signed release exists, there is a **test ZIP** (for test installations with synthetic data only, not signed): in GitHub, open the latest successful run of the workflow `ci` on branch `main` (event push, never a pull request), section Artifacts, `test-zip` (kept 14 days). GitHub wraps it once more; unpack the outer file first. Signed releases: `docs/admin/releases.md`.

## Install

Commands run in the folder with the file `artisan`. If the hosting calls PHP 8.4 differently (for example `php8.4`), use that name everywhere. An IP or password restriction belongs into the hosting panel; `public/.htaccess` opens its folder and is replaced by updates.

1. Unpack the ZIP into a folder outside every other web root and point the domain to its `public` folder, never to the folder itself.
2. First run. It writes `.env`, readable by you only, with a generated key:

   ```
   php artisan projektrack:install
   ```

3. Edit `.env`: replace every `CHANGE-ME`. Put a copy of `.env` into the password vault: without `APP_KEY` encrypted data is lost.
4. Second run, with your own values:

   ```
   php artisan projektrack:install --slug=acme --name="Acme d.o.o." --admin-email=admin@example.com --admin-name="First Last"
   ```

   Defaults: Bosnian, BA, BAM, Europe/Sarajevo (`--locale`, `--country`, `--currency`, `--timezone`). If something is missing the command says what and changes nothing.
5. Cron, every minute. It runs all background jobs, so without it no mail is sent:

   ```
   * * * * * php /path/to/projektrack/artisan schedule:run >/dev/null 2>&1
   ```

6. Open the link in the mail, set the password, sign in, turn on two-factor sign-in (required for administrators).
7. Open "System check" and run it.

Check that `https://<host>/.env` and `https://<host>/composer.json` answer 403 or 404.

## Test machine without HTTPS and mail server

Only for a machine that is not reachable from the internet. In `.env`:

```
APP_ENV=staging
APP_URL=http://test.localhost
MAIL_MAILER=log
LOG_LEVEL=debug
```

Remaining `CHANGE-ME` values of the mail server do not matter then. After cron ran once (`php artisan schedule:run`), the setup link is in the newest file of `storage/logs/`. That log holds sign-in links: never use these values on a reachable server.

## Sample data

On a test installation (`APP_ENV=staging`), for an empty company:

```
php artisan projektrack:demo <slug>
```

Everything in it is synthetic; nobody can sign in as a sample person, and no invoice is issued.

## What can be tested today

| Area | State |
|---|---|
| Password setup, sign-in, two-factor, sessions, profile | ready |
| Languages and own wording (bs, en, de), right to left | ready |
| Brand, settings, light and dark mode | ready |
| License screen (works without a license as Community) | ready |
| System check, audit log, notifications, API tokens | ready |
| Users and roles, dashboard | ready |
| Customers, projects, contracts, work packages | ready |
| Weekly timesheet, approval, corrections, month close | ready |
| Hourly rates, service reports (PDF, CSV) | ready |
| Invoices for sellers in DE and BA, cancellation, payments (PDF) | ready; legal notices must be confirmed by an accountant |
| Timer, XLSX, DOCX, invoices by email, ZUGFeRD and XRechnung (CII, UBL) | ready; not yet checked with the official validators (KoSIT, veraPDF) |
| Quotes, expenses, absences, down payments | not built yet |
| Encrypted backup and restore on plain hosting | ready |
| Web installer, Nginx sample | not built yet |

## Backup

1. `php artisan projektrack:backup:key`. Put the line `BACKUP_PUBLIC_KEY` into `.env` and the secret key into the password vault, never on the server.
2. From then on cron writes an encrypted backup every night into `storage/backups` (kept 14 days). `php artisan projektrack:backup` writes one at once.
3. Copy the backups off the server regularly, together with one copy of `.env`: a backup does not hold it, and without `APP_KEY` encrypted values are lost.

Restore, into an empty installation of the same version (`.env` of the old one, `php artisan migrate --force`, not `projektrack:install`): `php artisan projektrack:restore <file> --key-file=<file with the secret key>`, then delete the key file. Not on PostgreSQL. Try a restore once before you rely on it.

## Newer ZIP

There is no automatic update on plain hosting yet.

1. Verify a signed release from the old folder (`docs/admin/releases.md`), then `php artisan projektrack:backup` and copy the file off the server.
2. `php artisan down`
3. Unpack the new ZIP into a new folder. Copy `.env` (`cp -p`, so it stays readable by you only) and `storage/app` from the old one.
4. In the new folder: `php artisan projektrack:install`. It migrates and keeps the data. A company that existed before the modules: `php artisan projektrack:modules:enable <slug> service_reports` and the same with `invoices` (both bring what they need).
5. Point the domain and cron to the new folder.

If step 4 fails: restore the database, run `php artisan up` in the old folder and keep the domain on it.
