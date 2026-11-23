#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI test of the Docker Compose stack (S-0923): start it as an operator would,
# check secrets, headers, health, workers and database rights, take a backup
# and restore it into a second, empty stack (the restore drill). Needs Docker
# with Compose v2 and curl; runs from the repository root.
set -Eeuo pipefail

cd "$(dirname "$0")/../../deploy/docker"

DOMAIN=projektrack.localhost
IMAGE=projektrack:ci
SECOND=projektrack-restore
failures=0

say() { printf '\n== %s\n' "$*"; }
ok() { printf 'ok    %s\n' "$*"; }
bad() { printf 'FAIL  %s\n' "$*"; failures=$((failures + 1)); }
check() { local name="$1"; shift; if "$@"; then ok "${name}"; else bad "${name}"; fi; }

compose() { docker compose "$@"; }
second() { PROJEKTRACK_SUBNET=172.31.241.0/24 PROXY_IP=172.31.241.10 COMPOSE_PROJECT_NAME="${SECOND}" docker compose "$@"; }

cleanup() {
    status=$?
    if [[ ${status} -ne 0 || ${failures} -ne 0 ]]; then
        compose ps -a || true
        compose logs --no-color --tail=200 || true
        second logs --no-color --tail=100 || true
    fi
    compose down -v --remove-orphans >/dev/null 2>&1 || true
    second --profile restore down -v --remove-orphans >/dev/null 2>&1 || true
    if [[ -d backups ]]; then
        docker run --rm --user 0:0 --entrypoint rm -v "$PWD/backups:/backups" "${IMAGE}" -rf /backups/. 2>/dev/null || true
    fi
    rm -rf .env backup-identity.txt backups
}
trap cleanup EXIT

# Waits until a service reports healthy (or, for one-shot services, exited 0).
wait_healthy() {
    local runner="$1" service="$2" state
    for _ in $(seq 1 90); do
        state="$(${runner} ps -a --format '{{.Health}}' "${service}" 2>/dev/null | head -n 1)"
        [[ "${state}" == 'healthy' ]] && return 0
        sleep 2
    done
    echo "${service} did not become healthy" >&2
    return 1
}

curl_https() { curl -sk --resolve "${DOMAIN}:443:127.0.0.1" "$@"; }

say 'Build the image'
docker build -f Dockerfile -t "${IMAGE}" ../..

say 'Write secrets'
./setup.sh --domain "${DOMAIN}" --email ops@example.test --image "${IMAGE}" >/dev/null
cat >> .env <<'EOF'
CADDY_GLOBAL_OPTIONS=local_certs
MAIL_MAILER=log
MAIL_FROM_ADDRESS=noreply@example.test
MAIL_FROM_NAME=Projektrack CI
EOF
check '.env is readable by the owner only' test "$(stat -c %a .env)" = 600
check 'backup key is readable by the owner only' test "$(stat -c %a backup-identity.txt)" = 600
for key in DB_PASSWORD DB_MIGRATOR_PASSWORD REDIS_PASSWORD; do
    check "${key} is a generated 40-character secret" grep -Eq "^${key}=[A-Za-z0-9]{40}$" .env
done
check 'APP_KEY is 32 random bytes' grep -Eq '^APP_KEY=base64:[A-Za-z0-9+/]{43}=$' .env
check 'setup refuses to overwrite secrets' bash -c '! ./setup.sh --domain other.localhost --email a@example.test --image '"${IMAGE}"' 2>/dev/null'
env_value() { grep "^$1=" .env | cut -d= -f2-; }
DB_PASSWORD="$(env_value DB_PASSWORD)"
DB_MIGRATOR_PASSWORD="$(env_value DB_MIGRATOR_PASSWORD)"

say 'Start the stack'
compose up -d
wait_healthy compose app
for service in proxy worker scheduler backup db redis; do
    check "${service} runs" test "$(compose ps --format '{{.State}}' "${service}")" = running
done
check 'migrations ran and exited' test "$(compose ps -a --format '{{.ExitCode}}' migrate)" = 0

say 'Create the tenant and its administrator'
compose exec -T app php artisan projektrack:tenant:create --slug=ci --name='CI Company d.o.o.' \
    --domain="${DOMAIN}" --admin-email=admin@example.test --admin-name='CI Admin'
for _ in $(seq 1 30); do
    compose logs worker 2>/dev/null | grep -q 'ResetPasswordLink.*DONE' && break
    sleep 2
done
check 'the worker sent the setup link' bash -c "docker compose logs worker | grep -q 'ResetPasswordLink.*DONE'"

say 'Headers and answers through the proxy'
login_headers="$(curl_https -o /dev/null -D - "https://${DOMAIN}/login")"
check 'sign-in page answers 200' grep -q '^HTTP/[0-9.]* 200' <<<"${login_headers}"
check 'X-Robots-Tag noindex' grep -qi '^x-robots-tag: noindex, nofollow, noarchive, nosnippet, noimageindex, notranslate' <<<"${login_headers}"
check 'Referrer-Policy no-referrer' grep -qi '^referrer-policy: no-referrer' <<<"${login_headers}"
check 'nonce-based CSP of the app' grep -qi "^content-security-policy: .*'nonce-" <<<"${login_headers}"
check 'HSTS of the app (request seen as HTTPS)' grep -qi '^strict-transport-security: max-age=31536000; includeSubDomains' <<<"${login_headers}"
check 'secure session cookie' grep -qi '^set-cookie: __Host-sid=.*secure' <<<"${login_headers}"
check 'no Server header' bash -c '! grep -qi "^server:" <<<"$1"' _ "${login_headers}"
check 'no X-Powered-By header' bash -c '! grep -qi "^x-powered-by:" <<<"$1"' _ "${login_headers}"
check 'each header once' bash -c '[[ $(grep -ci "^x-robots-tag:" <<<"$1") -eq 1 && $(grep -ci "^content-security-policy:" <<<"$1") -eq 1 ]]' _ "${login_headers}"

static_headers="$(curl_https -o /dev/null -D - "https://${DOMAIN}/favicon.svg")"
check 'static file: strict CSP' grep -qi "^content-security-policy: default-src 'none'" <<<"${static_headers}"
check 'static file: X-Robots-Tag' grep -qi '^x-robots-tag: noindex' <<<"${static_headers}"
check 'static file: no Server header' bash -c '! grep -qi "^server:" <<<"$1"' _ "${static_headers}"

check 'robots.txt disallows everything' bash -c 'curl -sk --resolve "$1:443:127.0.0.1" "https://$1/robots.txt" | grep -q "^Disallow: /$"' _ "${DOMAIN}"

redirect="$(curl -s -o /dev/null -D - --resolve "${DOMAIN}:80:127.0.0.1" "http://${DOMAIN}/login")"
check 'HTTP redirects to HTTPS' grep -qi "^location: https://${DOMAIN}/login" <<<"${redirect}"
check 'redirect carries X-Robots-Tag' grep -qi '^x-robots-tag: noindex' <<<"${redirect}"

unknown="$(curl -s -o /dev/null -D - -H 'Host: unknown.example.test' http://127.0.0.1/)"
check 'unknown host: 404' grep -q '^HTTP/[0-9.]* 404' <<<"${unknown}"
check 'unknown host: X-Robots-Tag' grep -qi '^x-robots-tag: noindex' <<<"${unknown}"

check 'health from outside: 404' test "$(curl_https -o /dev/null -w '%{http_code}' "https://${DOMAIN}/health")" = 404
check 'health inside the container: OK' test "$(compose exec -T app wget -q -O - http://127.0.0.1:8080/health)" = OK
check 'forged X-Forwarded-For is ignored' test "$(curl_https -o /dev/null -w '%{http_code}' -H 'X-Forwarded-For: 127.0.0.1' "https://${DOMAIN}/health")" = 404
check 'PHP files other than the front controller do not exist' test "$(curl_https -o /dev/null -w '%{http_code}' "https://${DOMAIN}/index.php")" = 404

say 'Database rights of the runtime account'
runtime_sql() { compose exec -T -e MYSQL_PWD="${DB_PASSWORD}" db mariadb -uprojektrack projektrack -e "$1"; }
check 'runtime account reads' runtime_sql 'SELECT COUNT(*) FROM tenants'
check 'runtime account cannot change the audit log' bash -c '! docker compose exec -T -e MYSQL_PWD="$1" db mariadb -uprojektrack projektrack -e "DELETE FROM audit_log" 2>/dev/null' _ "${DB_PASSWORD}"
check 'runtime account cannot change the schema' bash -c '! docker compose exec -T -e MYSQL_PWD="$1" db mariadb -uprojektrack projektrack -e "CREATE TABLE probe (id INT)" 2>/dev/null' _ "${DB_PASSWORD}"
check 'the app container has no migrations password' bash -c '! docker compose exec -T app env | grep -q "$1"' _ "${DB_MIGRATOR_PASSWORD}"

say 'Backup'
compose exec -T app sh -c 'mkdir -p storage/app/private && printf "restore drill\n" > storage/app/private/drill.txt'
compose exec -T backup projektrack-backup run
stamp="$(docker run --rm --user 0:0 --entrypoint ls -v "$PWD/backups:/backups:ro" "${IMAGE}" /backups | grep -E '^[0-9]{8}T[0-9]{6}Z$' | tail -n 1)"
check 'a backup folder was written' test -n "${stamp}"
check 'backup files are encrypted' bash -c 'docker run --rm --user 0:0 --entrypoint sh -v "$PWD/backups:/backups:ro" "$1" -c "head -c 21 /backups/$2/database.sql.gz.age" | grep -q "age-encryption.org/v1"' _ "${IMAGE}" "${stamp}"
check 'backup health check passes' compose exec -T backup projektrack-backup check

dump() { "$1" exec -T -e MYSQL_PWD="${DB_MIGRATOR_PASSWORD}" db mariadb-dump -uprojektrack_migrator --single-transaction --skip-dump-date --skip-comments --no-tablespaces projektrack | sha256sum; }
files() { "$1" exec -T "$2" sh -c 'cd /app/storage && find app -type f -exec sha256sum {} + | sort' | sha256sum; }
before_db="$(dump compose)"
before_files="$(files compose app)"

say 'Restore drill into a second, empty stack'
second up -d db redis
wait_healthy second db
second --profile restore run --rm -T restore restore "${stamp}"
after_db="$(dump second)"
check 'database identical after restore' test "${before_db}" = "${after_db}"
second up -d app
wait_healthy second app
after_files="$(files second app)"
check 'files identical after restore' test "${before_files}" = "${after_files}"
check 'restored instance is healthy' test "$(second exec -T app wget -q -O - http://127.0.0.1:8080/health)" = OK
check 'restore refuses a non-empty instance' bash -c '! PROJEKTRACK_SUBNET=172.31.241.0/24 PROXY_IP=172.31.241.10 COMPOSE_PROJECT_NAME="$1" docker compose --profile restore run --rm -T restore restore "$2" 2>/dev/null' _ "${SECOND}" "${stamp}"

say "Result: ${failures} failed"
[[ ${failures} -eq 0 ]]
