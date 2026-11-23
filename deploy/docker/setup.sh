#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Writes the .env of the Docker Compose stack once (S-0923): every secret is
# generated from the system's random source, none is a default. Also creates
# the backup key pair: the public key goes into .env, the private key into
# backup-identity.txt, which belongs off this host (see docs/admin/docker.md).
#
#   ./setup.sh --domain app.example.com --email ops@example.com [--image <ref>]
set -Eeuo pipefail

cd "$(dirname "$0")"

fail() { echo "setup: $*" >&2; exit 1; }

domain=''
email=''
image="${PROJEKTRACK_IMAGE:-projektrack:local}"
# Frontend network: the proxy has a fixed address below the range Docker
# hands out dynamically, so no other container can ever take it.
prefix='172.31.240'

while [[ $# -gt 0 ]]; do
    case "$1" in
        --domain) domain="${2:-}"; shift 2 ;;
        --email) email="${2:-}"; shift 2 ;;
        --image) image="${2:-}"; shift 2 ;;
        --network) prefix="${2:-}"; shift 2 ;;
        *) fail "unknown option $1" ;;
    esac
done

[[ "${prefix}" =~ ^(10\.[0-9]{1,3}|172\.(1[6-9]|2[0-9]|3[01])|192\.168)\.[0-9]{1,3}$ ]] \
    || fail 'Give --network as the first three parts of a private /24 network, for example 172.31.240.'
subnet="${prefix}.0/24"
ip_range="${prefix}.128/25"
proxy_ip="${prefix}.10"

domain="${domain,,}"
[[ "${domain}" =~ ^([a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$ ]] || fail 'Give the host name with --domain, for example app.example.com.'
[[ "${email}" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || fail 'Give the operator address with --email (certificate notices).'
[[ "${image}" =~ ^[A-Za-z0-9._/:@-]+$ ]] || fail 'The image reference has unexpected characters.'
[[ -e .env ]] && fail '.env exists already. Secrets are written once; remove it only to start a new instance.'
[[ -e backup-identity.txt ]] && fail 'backup-identity.txt exists already.'
command -v docker >/dev/null || fail 'Docker is not installed.'

umask 077

# Letters and digits only, so no value needs quoting anywhere.
secret() { LC_ALL=C tr -dc 'A-Za-z0-9' < /dev/urandom 2>/dev/null | head -c "${1:-40}" || true; }
app_key="base64:$(head -c 32 /dev/urandom | base64 | tr -d '\n')"
db_password="$(secret 40)"
db_migrator_password="$(secret 40)"
redis_password="$(secret 40)"
backup_hmac_key="$(secret 40)"
for value in "${db_password}" "${db_migrator_password}" "${redis_password}" "${backup_hmac_key}"; do
    [[ ${#value} -eq 40 ]] || fail 'Could not read the random source.'
done
[[ ${#app_key} -eq 51 ]] || fail 'Could not read the random source.'

# The backup key pair, made with the age tool of the application image.
docker run --rm --network none --entrypoint age-keygen "${image}" > backup-identity.txt 2>/dev/null \
    || fail "Could not run ${image}; build it (docker compose build app) or pull it first."
recipient="$(grep -o 'age1[0-9a-z]*' backup-identity.txt | head -n 1)"
[[ -n "${recipient}" ]] || fail 'No public key in backup-identity.txt.'

# The backup folder belongs to the user of the application image (82).
mkdir -p backups
docker run --rm --network none --user 0:0 --entrypoint chown -v "$PWD/backups:/backups" "${image}" 82:82 /backups

cat > .env <<EOF
# Written by setup.sh on $(date -u +%Y-%m-%d). Keep a copy of this file and of
# backup-identity.txt in the password vault: a restore needs both.
PROJEKTRACK_IMAGE=${image}
PROJEKTRACK_DOMAIN=${domain}
ACME_EMAIL=${email}

APP_KEY=${app_key}
DB_PASSWORD=${db_password}
DB_MIGRATOR_PASSWORD=${db_migrator_password}
REDIS_PASSWORD=${redis_password}
BACKUP_AGE_RECIPIENT=${recipient}
BACKUP_HMAC_KEY=${backup_hmac_key}

# Frontend network; the proxy address is the only trusted proxy of the app and
# lies outside the range of dynamic addresses.
PROJEKTRACK_SUBNET=${subnet}
PROJEKTRACK_IP_RANGE=${ip_range}
PROXY_IP=${proxy_ip}

# Who may ask GET /health (addresses or CIDR ranges, comma-separated).
HEALTH_ALLOWED_IPS=127.0.0.1

BACKUP_DIR=./backups
BACKUP_INTERVAL_HOURS=6
BACKUP_KEEP_DAYS=14

APP_LOCALE=bs
DOCTOR_OPERATOR_TENANT=

# Implicit TLS by default; STARTTLS on 587 could be stripped on the way.
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=
MAIL_PORT=465
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
EOF

cat <<EOF
Written .env and backup-identity.txt.

Next:
  1. Fill in the MAIL_ values in .env (the setup link of the first administrator goes by mail).
  2. Move backup-identity.txt off this host into the password vault, together with a copy of .env.
  3. docker compose up -d
  4. docker compose exec app php artisan projektrack:tenant:create --slug=... --name=... \\
       --domain=${domain} --admin-email=... --admin-name=...
EOF
