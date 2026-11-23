#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Restores a backup of projektrack-backup into an empty instance (S-0923).
#
#   projektrack-restore <backup folder name, for example 20261123T020000Z>
#
# Needs the private backup key at /run/secrets/backup-identity, the database
# account of the migrations (DB_USERNAME) and an empty database and storage.
# It checks the checksums before it changes anything and refuses to restore
# over existing data. The application must use the APP_KEY of the instance
# the backup came from, or encrypted values cannot be read.
set -Eeuo pipefail

IDENTITY=/run/secrets/backup-identity

log() { printf '%s restore: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }
fail() { log "$*"; exit 1; }

stamp="${1:-}"
[[ "${stamp}" =~ ^[0-9]{8}T[0-9]{6}Z$ ]] || fail 'Usage: projektrack-restore <backup folder name, for example 20261123T020000Z>'
source="/backups/${stamp}"

[[ -d "${source}" ]] || fail "No backup ${stamp} in /backups."
[[ -r "${IDENTITY}" ]] || fail "The private backup key is not readable at ${IDENTITY}."

log 'checking checksums'
(cd "${source}" && sha256sum -c SHA256SUMS >/dev/null) || fail 'Checksums do not match: the backup is damaged or was changed.'

export MYSQL_PWD="${DB_PASSWORD}"
sql=(mariadb --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" --batch --skip-column-names)

for _ in $(seq 1 60); do
    "${sql[@]}" --execute='SELECT 1' >/dev/null 2>&1 && break
    sleep 2
done

tables="$("${sql[@]}" --execute="SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = '${DB_DATABASE}'")"
[[ "${tables}" == '0' ]] || fail "The database ${DB_DATABASE} is not empty; restore only into a new instance."

if [[ -n "$(find /app/storage -mindepth 1 -path /app/storage/framework -prune -o -path /app/storage/logs -prune -o -type f -print -quit)" ]]; then
    fail 'The storage volume is not empty; restore only into a new instance.'
fi

log 'restoring the database'
age --decrypt --identity "${IDENTITY}" "${source}/database.sql.gz.age" | gunzip | "${sql[@]}" "${DB_DATABASE}"

log 'restoring the files'
age --decrypt --identity "${IDENTITY}" "${source}/files.tar.gz.age" | gunzip | tar -C /app/storage -xf - --same-owner

unset MYSQL_PWD
log "restored ${stamp}; start the stack now (docker compose up -d)"
