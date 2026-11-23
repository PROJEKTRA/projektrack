#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Local snapshot for the automatic rollback of an update (S-0927). The
# encrypted backups cannot serve for that: their private key is off this host.
# The snapshot lives in its own volume, next to the database volume that holds
# the same data, only while an update runs, and is removed afterwards.
#
#   projektrack-snapshot check|create|restore|drop
set -Eeuo pipefail

DIR=/snapshot
umask 077

fail() { printf '%s snapshot: %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; exit 1; }

export MYSQL_PWD="${DB_PASSWORD}"
account=(--host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}")
sql=(mariadb "${account[@]}" --batch --skip-column-names "${DB_DATABASE}")

drop() { rm -f "${DIR}/database.sql.gz" "${DIR}/files.tar.gz" "${DIR}/SHA256SUMS" "${DIR}/SHA256SUMS.partial"; }

# Before an update touches anything: no snapshot is left, and the disk holds
# the snapshot and a restore from it.
check() {
    [[ ! -e "${DIR}/SHA256SUMS" ]] || fail 'A snapshot of an earlier update exists. See docs/admin/docker.md.'
    local database files free need
    database="$("${sql[@]}" --execute="SELECT COALESCE(CEIL(SUM(data_length + index_length) / 1024), 0) FROM information_schema.tables WHERE table_schema = DATABASE()")"
    files="$(du -sk /app/storage | cut -f1)"
    free="$(df -Pk "${DIR}" | awk 'NR == 2 { print $4 }')"
    need=$(( 2 * (database + files) + 524288 ))
    [[ "${free}" -ge "${need}" ]] || fail "Not enough disk space: ${need} KB needed, ${free} KB free."
}

create() {
    # Left over from an interrupted update: somebody has to look first.
    [[ ! -e "${DIR}/SHA256SUMS" ]] || fail 'A snapshot of an earlier update exists. See docs/admin/docker.md.'
    drop
    mariadb-dump "${account[@]}" --single-transaction --quick --no-tablespaces \
        --default-character-set=utf8mb4 "${DB_DATABASE}" | gzip -6 > "${DIR}/database.sql.gz"
    tar -C /app/storage --exclude=./framework --exclude=./logs -czf "${DIR}/files.tar.gz" .
    # Written last: its presence means the snapshot is complete.
    # Synced before and after, so a power loss cannot leave a damaged snapshot
    # next to a changed database.
    (cd "${DIR}" && sha256sum database.sql.gz files.tar.gz > SHA256SUMS.partial)
    sync
    mv "${DIR}/SHA256SUMS.partial" "${DIR}/SHA256SUMS"
    sync
}

restore() {
    [[ -e "${DIR}/SHA256SUMS" ]] || fail 'There is no complete snapshot.'
    (cd "${DIR}" && sha256sum -c SHA256SUMS >/dev/null) || fail 'The snapshot is damaged; nothing was changed.'

    # Tables of the failed update go too, so the schema is exactly the old one.
    {
        echo 'SET FOREIGN_KEY_CHECKS = 0;'
        "${sql[@]}" --execute="SELECT CONCAT('DROP TABLE \`', table_name, '\`;') FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'"
    } | "${sql[@]}"
    gunzip -c "${DIR}/database.sql.gz" | "${sql[@]}"

    find /app/storage -mindepth 1 -maxdepth 1 ! -name framework ! -name logs -exec rm -rf {} +
    tar -C /app/storage -xzf "${DIR}/files.tar.gz"
}

case "${1:-}" in
    check | create | restore | drop) "$1" ;;
    *) fail 'Usage: projektrack-snapshot check|create|restore|drop' ;;
esac
