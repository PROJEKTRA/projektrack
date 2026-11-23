# Running Projektrack with Docker Compose

For operators of the Docker Compose profile and of managed instances (S-0923, infrastructure profiles sections 4 and 7). All files are in `deploy/docker/`.

## What runs

| Service | Image | Job |
|---|---|---|
| `proxy` | Caddy | The only service with published ports (80, 443). Automatic TLS, mandatory headers, no `Server` header, no access log. |
| `app` | Projektrack | Nginx and PHP-FPM as an unprivileged user, read-only root file system. |
| `worker` | Projektrack | Queue worker (mails, notifications). |
| `scheduler` | Projektrack | Scheduled tasks (system check, audit verification). |
| `migrate` | Projektrack | Runs on every start: migrations, permission catalog, database rights. Then exits. |
| `backup` | Projektrack | Encrypted backups every 6 hours, kept 14 days. |
| `db` | MariaDB 11.4 | Data volume, not reachable from outside. |
| `redis` | Valkey 8 | Cache and queue, password protected, internal only. |

Two networks: `frontend` (proxy, app, worker, scheduler; the worker needs it to send mail) and the internal `backend` without internet access (database, cache, migrations, backups). The proxy cannot reach the database. It has a fixed address that the application trusts as its only proxy; Docker hands out other addresses only from the upper half of the network, so no container can take it.

Two database accounts: `projektrack_migrator` owns the schema; the application runs as `projektrack`, which reads and writes rows, cannot change the schema and can only add to the audit log. The database root password is random and never needed.

Not yet in the stack: Meilisearch, Gotenberg, MinIO, ClamAV and Reverb. The application uses its built-in fallbacks for them (infrastructure profiles section 2).

## Requirements

Ubuntu 24.04 LTS, Docker Engine 27 or newer with Compose v2, 2 vCPU, 4 GB RAM, 40 GB disk, ports 80 and 443 reachable, and a DNS name pointing to the host. Harden the host as in infrastructure profiles section 7 (SSH keys only, firewall, unattended upgrades).

## First installation

1. Get the image. Verify the first signed release (`docs/admin/releases.md`) and use the image named in its `image.txt`. Before that exists, build it from the repository root: `docker build -f deploy/docker/Dockerfile -t projektrack:local .`
2. In `deploy/docker/`, write the secrets once:

   ```
   ./setup.sh --domain app.example.com --email ops@example.com --image projektrack:local
   ```

   This writes `.env` and `backup-identity.txt` (both readable by you only), and the folder `backups/`. Every secret is generated; the script refuses to overwrite an existing `.env`. If the network `172.31.240.0/24` is in use on the host, add `--network 10.99.7` (the first three parts of a free private /24 network). Build from a clean checkout: everything in the folder that is not excluded ends up in the image.
3. Enter the mail server in the `MAIL_` lines of `.env`. The default is implicit TLS on port 465 (`MAIL_SCHEME=smtps`); keep it, because STARTTLS on port 587 can be stripped on the way. `projektrack:tenant:create` refuses to run while no mail server is set.
4. Move `backup-identity.txt` off the host into the password vault, together with a copy of `.env`. Without both, no backup can be restored.
5. Start the stack: `docker compose up -d`. Caddy obtains the certificate on the first request.
6. Create the tenant and its first administrator:

   ```
   docker compose exec app php artisan projektrack:tenant:create --slug=acme --name="Acme d.o.o." \
     --domain=app.example.com --admin-email=admin@example.com --admin-name="Ana Admin" \
     --locale=bs --country=BA --currency=BAM --timezone=Europe/Sarajevo
   ```

   The administrator receives a setup link by mail, valid for 60 minutes, and sets their own password and second factor. The link is never shown to the operator. If it expires, the administrator uses "Forgot password" on the sign-in page.
7. Check from outside: `docker compose exec app php artisan projektrack:doctor`.

The certificate is published in Certificate Transparency logs; choose an unobvious host name (infrastructure profiles section 7).

Publish an A record only, no AAAA record. The stack network is IPv4; Docker would pass IPv6 visitors on with one shared internal address, so sign-in limits would count all of them as one visitor.

## Health check

`GET /health` answers `OK` only to the addresses in `HEALTH_ALLOWED_IPS` (default: the container itself). Add the address of your monitoring host, comma-separated, and run `docker compose up -d` again. The `app` container reports its own health to Docker.

## Backups

The `backup` service writes a folder `backups/<UTC time>/` every 6 hours (`BACKUP_INTERVAL_HOURS`) and removes folders older than 14 days (`BACKUP_KEEP_DAYS`). Each folder holds:

- `database.sql.gz.age`: the database,
- `files.tar.gz.age`: uploaded files and keys from the storage volume (no caches or logs),
- `SHA256SUMS`: checksums of both,
- `SHA256SUMS.hmac`: a signature of the checksums with `BACKUP_HMAC_KEY` from `.env`.

Both files are encrypted to the public key in `.env` while they are written; nothing unencrypted reaches the disk. Only the private key in the vault can decrypt them, so copying the folder off-site (for example with `rsync` or `rclone` from the host) keeps it confidential. Because the public key is not secret, anyone could encrypt a backup of their own; the signature is what proves that a backup comes from this instance, and a restore refuses any backup without a valid one.

Today backups stay on the host until you copy them off-site yourself. Set that up from day one for a managed instance (second EU location, infrastructure profiles section 7). The `backup` container turns unhealthy when the last success is older than the interval plus one hour; monitor that state.

A backup now: `docker compose exec backup projektrack-backup run`.

## Restore drill

Rehearse monthly on a scratch host, and restore the same way after a loss. CI runs this drill on every change (`tests/docker/stack.sh`).

1. On the new host, put the vault copies of `.env` and `backup-identity.txt` into `deploy/docker/` (`chmod 600` both), and the backup folders into `backups/`. The same `APP_KEY` is needed, or encrypted values cannot be read.
2. Start only the database and cache: `docker compose up -d db redis`
3. Restore one backup into the empty instance:

   ```
   docker compose --profile restore run --rm restore restore 20261123T020000Z
   ```

   The script checks the signature and the checksums and decrypts both files once as a trial before it writes anything. It refuses to write into a database or storage volume that holds data. `BACKUP_HMAC_KEY` in `.env` must be the one of the instance the backup came from.
4. Start everything: `docker compose up -d`. The `migrate` service brings the schema to the version of the image and sets the database rights.
5. Remove `backup-identity.txt` from the host again.

Targets for managed instances: RPO 6 hours, RTO 4 hours (business hours).

## Logs

Containers log to Docker (`docker compose logs`), rotated at 10 x 10 MB per container. No log contains request paths, because paths can carry one-time tokens (password reset, shared links): access logs leave them out, Nginx logs only critical errors and Caddy's request error entries are switched off. For 90-day retention on managed instances, ship the Docker logs to the host's journal.

## Updates

Download `SHA256SUMS`, `SHA256SUMS.sig` and `projektrack-X.Y.Z.image.txt` of the release into an empty folder on the host, then in `deploy/docker/`:

```
./projektrack update /path/to/folder
```

The command:

1. verifies the signature with the release keys of the running version (`docs/admin/releases.md`), takes the image digest from the verified file, and refuses a version that is not newer than the running one;
2. checks the health and the disk space, takes an encrypted backup, enables maintenance mode, ends running requests and jobs and stops the background services;
3. takes a local snapshot of database and files, then runs the migrations of the new version (visitors see the maintenance page);
4. stops the proxy, starts the new version, checks its health and opens it again.

If a step fails, it restores the snapshot, starts the previous version, checks its health and ends with exit code 1. The snapshot is needed because the private key of the backups is off the host; it sits in its own Docker volume only while an update runs.

If the new version is healthy but a service does not start afterwards, there is no rollback any more, because users may already have written data; the command says so.

### When an update was interrupted

Exit code 2 means the rollback failed too: the proxy stays stopped and the snapshot is kept. A power loss leaves the file `.update-state` with the previous image. The command refuses further updates until this is resolved:

1. Stop everything that writes: `docker compose stop proxy app worker scheduler backup`, and remove leftovers with `docker rm -f projektrack-update-snapshot projektrack-update-migrate`.
2. If `.update-state` contains `committed=1`, the new version was healthy and may already hold new data: keep it and skip to step 5.
3. Set `PROJEKTRACK_IMAGE` in `.env` to `previous` from `.update-state`.
4. Restore: `docker compose --profile update run --rm snapshot projektrack-snapshot restore`. If it says that there is no complete snapshot, the database was not touched yet (without `committed=1`). If it says that the snapshot is damaged, restore the encrypted backup on a new host.
5. Start only the application, with the proxy still stopped: `docker compose up -d --no-deps app`, `docker compose exec app php artisan up`, and check `docker compose exec app wget -q -O - http://127.0.0.1:8080/health` answers `OK` (it takes a few seconds after the start).
6. Only when it is fine: `docker compose --profile update run --rm snapshot projektrack-snapshot drop`, delete `.update-state` and open the instance with `docker compose up -d`.

The snapshot holds unencrypted data, like the database volume. Exclude the volume `projektrack_snapshot` from host backups.

Pre-releases such as `0.9.0-rc1` count as older than `0.9.0` and can be installed like any signed release.
The command does not update the files in `deploy/docker/` themselves; take those from the release ZIP when its notes say so, after verifying it (`docs/admin/releases.md`).

## Commands

| Command | Does |
|---|---|
| `./projektrack install --domain ... --email ... --image ...` | Writes the secrets (`setup.sh`) |
| `./projektrack backup` | One encrypted backup now |
| `./projektrack restore <backup>` | Restores a backup into an empty instance and starts it |
| `./projektrack update <folder>` | Update with automatic rollback |
