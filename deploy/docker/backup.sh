#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Encrypted backups of the Docker Compose stack (S-0923).
#
#   projektrack-backup loop   back up whenever the last success is older than
#                             BACKUP_INTERVAL_HOURS (default 6); check every 5 minutes
#   projektrack-backup run    one backup now
#   projektrack-backup check  exit 1 when the last success is too old (health check)
#
# A backup is one folder /backups/<UTC time> with database.sql.gz.age (the
# database), files.tar.gz.age (storage without caches and logs), SHA256SUMS
# and SHA256SUMS.hmac (HMAC-SHA256 with BACKUP_HMAC_KEY, so a restore accepts
# only backups made by this instance).
# Both files are encrypted to the age public key BACKUP_AGE_RECIPIENT as they
# are written; nothing unencrypted touches the disk. The private key is kept
# off the host, so a copy of the backups alone reveals nothing.
set -Eeuo pipefail

BACKUP_DIR=/backups
INTERVAL_HOURS="${BACKUP_INTERVAL_HOURS:-6}"
KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
MARKER="${BACKUP_DIR}/.last-success"

log() { printf '%s backup: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }

projektrack_hmac() {
    php -r 'echo hash_hmac("sha256", file_get_contents($argv[1]), getenv("BACKUP_HMAC_KEY")), "\n";' "$1"
}

require_settings() {
    : "${BACKUP_AGE_RECIPIENT:?BACKUP_AGE_RECIPIENT is not set}"
    local key="${BACKUP_HMAC_KEY:-}"
    [[ ${#key} -ge 32 ]] || { log 'BACKUP_HMAC_KEY is missing or too short.'; exit 2; }
    [[ "${INTERVAL_HOURS}" =~ ^[1-9][0-9]*$ ]] || { log 'BACKUP_INTERVAL_HOURS must be a whole number of hours.'; exit 2; }
    [[ "${KEEP_DAYS}" =~ ^[1-9][0-9]*$ ]] || { log 'BACKUP_KEEP_DAYS must be a whole number of days.'; exit 2; }
}

age_seconds() {
    if [[ -f "${MARKER}" ]]; then
        echo $(( $(date +%s) - $(stat -c %Y "${MARKER}") ))
    else
        echo 999999999
    fi
}

run_backup() {
    require_settings
    umask 077
    local stamp work
    stamp="$(date -u +%Y%m%dT%H%M%SZ)"
    work="${BACKUP_DIR}/.${stamp}.partial"
    mkdir "${work}"
    trap 'rm -rf "${work}"' ERR

    log "starting ${stamp}"
    MYSQL_PWD="${DB_PASSWORD}" mariadb-dump --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" \
        --single-transaction --quick --no-tablespaces --skip-dump-date --default-character-set=utf8mb4 \
        "${DB_DATABASE}" | gzip -6 | age --encrypt --recipient "${BACKUP_AGE_RECIPIENT}" --output "${work}/database.sql.gz.age"

    tar -C /app/storage --exclude=./framework --exclude=./logs -cf - . |
        gzip -6 | age --encrypt --recipient "${BACKUP_AGE_RECIPIENT}" --output "${work}/files.tar.gz.age"

    (cd "${work}" && sha256sum database.sql.gz.age files.tar.gz.age > SHA256SUMS)
    # The public key alone could forge a backup; the HMAC with a secret key proves its origin.
    projektrack_hmac "${work}/SHA256SUMS" > "${work}/SHA256SUMS.hmac"
    mv "${work}" "${BACKUP_DIR}/${stamp}"
    trap - ERR
    touch "${MARKER}"
    log "done ${stamp}"

    # Retention: backups older than BACKUP_KEEP_DAYS, and partial leftovers.
    find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -name '[0-9]*T*Z' -mtime +"${KEEP_DAYS}" -exec rm -rf {} +
    find "${BACKUP_DIR}" -mindepth 1 -maxdepth 1 -type d -name '.*.partial' -mmin +720 -exec rm -rf {} +
}

case "${1:-loop}" in
    run)
        run_backup
        ;;
    check)
        require_settings
        [[ "$(age_seconds)" -lt $(( (INTERVAL_HOURS + 1) * 3600 )) ]]
        ;;
    loop)
        require_settings
        log "every ${INTERVAL_HOURS} h, kept ${KEEP_DAYS} days"
        while true; do
            if [[ "$(age_seconds)" -ge $(( INTERVAL_HOURS * 3600 )) ]]; then
                # Its own process: inside "||" bash would ignore errexit.
                "$0" run || log 'FAILED, next attempt in 5 minutes'
            fi
            sleep 300 &
            wait $!
        done
        ;;
    *)
        echo 'Usage: projektrack-backup [loop|run|check]' >&2
        exit 2
        ;;
esac
