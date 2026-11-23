#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Roles of the application image (S-0923):
#   web        Nginx and PHP-FPM on port 8080
#   worker     queue worker
#   scheduler  scheduled tasks
#   migrate    database migrations and the grants of the runtime user, then exit
#   backup     encrypted backups at an interval (projektrack-backup)
#   restore    restore a backup into an empty instance (projektrack-restore)
# Anything else runs as a command, for example "php artisan about".
set -Eeuo pipefail

cd /app

log() { printf '%s %s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$*" >&2; }

# Folders of the storage volume, and the framework caches in the tmpfs over
# bootstrap/cache (the root file system is read-only).
prepare() {
    mkdir -p storage/app/private storage/app/public storage/framework/cache/data \
        storage/framework/sessions storage/framework/views storage/logs
    cp -a bootstrap/cache.dist/. bootstrap/cache/
    php artisan config:cache --no-interaction >/dev/null
    php artisan event:cache --no-interaction >/dev/null
    php artisan route:cache --no-interaction >/dev/null
    php artisan projektrack:strings:cache --no-interaction >/dev/null
}

# Waits until the database answers, at most two minutes.
wait_for_database() {
    for _ in $(seq 1 60); do
        if php artisan db:show --no-interaction >/dev/null 2>&1; then
            return 0
        fi
        sleep 2
    done
    log 'The database did not answer within two minutes.'
    return 1
}

# The runtime user reads and writes rows but changes no schema, and may only
# add to the audit log (infrastructure profiles 1.2, security baseline 8).
grant_runtime_user() {
    : "${DB_RUNTIME_USERNAME:?DB_RUNTIME_USERNAME is not set}"
    export MYSQL_PWD="${DB_PASSWORD}"
    local sql=(mariadb --host="${DB_HOST}" --port="${DB_PORT:-3306}" --user="${DB_USERNAME}" --batch --skip-column-names "${DB_DATABASE}")

    "${sql[@]}" --execute="SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'" |
        while IFS= read -r table; do
            if [[ ! "${table}" =~ ^[A-Za-z0-9_]+$ ]]; then
                log "Skipping a table with an unexpected name."
                continue
            fi
            if [[ "${table}" == 'audit_log' ]]; then
                printf "GRANT SELECT, INSERT ON \`%s\` TO '%s'@'%%';\n" "${table}" "${DB_RUNTIME_USERNAME}"
            else
                printf "GRANT SELECT, INSERT, UPDATE, DELETE ON \`%s\` TO '%s'@'%%';\n" "${table}" "${DB_RUNTIME_USERNAME}"
            fi
        done | "${sql[@]}"
    unset MYSQL_PWD
}

role="${1:-web}"

case "${role}" in
    web)
        prepare
        php artisan view:cache --no-interaction >/dev/null
        php-fpm --nodaemonize &
        nginx -e /dev/stderr -g 'daemon off;' &
        # If one of the two stops, the container stops and Docker restarts it.
        wait -n
        exit 1
        ;;
    worker)
        prepare
        exec php artisan queue:work --sleep=1 --tries=3 --max-time=3600 --memory=256 --no-interaction
        ;;
    scheduler)
        prepare
        exec php artisan schedule:work --no-interaction
        ;;
    migrate)
        prepare
        wait_for_database
        php artisan migrate --force --no-interaction
        php artisan projektrack:permissions:sync --no-interaction
        grant_runtime_user
        log 'Migrations and grants done.'
        ;;
    backup)
        shift
        exec projektrack-backup "${@:-loop}"
        ;;
    restore)
        shift
        exec projektrack-restore "$@"
        ;;
    *)
        exec "$@"
        ;;
esac
