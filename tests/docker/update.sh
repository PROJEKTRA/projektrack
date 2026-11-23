#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI test of the operations CLI (S-0927): an update, refused updates, and two
# forced failures (migration, health) that must roll back to the same data and
# the previous version. Images come from a local registry; cosign is a stand-in
# in PATH, so the product has no switch that skips the verification.
set -Eeuo pipefail

cd "$(dirname "$0")/../../deploy/docker"

DOMAIN=projektrack.localhost
REPO=127.0.0.1:5000/projektrack
failures=0
work="$(mktemp -d)"

say() { printf '\n== %s\n' "$*"; }
check() { local name="$1"; shift; if "$@"; then printf 'ok    %s\n' "${name}"; else printf 'FAIL  %s\n' "${name}"; failures=$((failures + 1)); fi; }

cleanup() {
    status=$?
    if [[ ${status} -ne 0 || ${failures} -ne 0 ]]; then
        docker compose ps -a || true
        docker compose logs --no-color --tail=120 || true
    fi
    docker compose --profile update down -v --remove-orphans >/dev/null 2>&1 || true
    docker rm -f ci-registry >/dev/null 2>&1 || true
    docker run --rm --user 0:0 --network none --entrypoint sh -v "$PWD/backups:/backups" projektrack:v1 -c 'rm -rf /backups/* /backups/.[!.]*' 2>/dev/null || true
    rm -rf .env .lock backup-identity.txt backups "${work}"
}
trap cleanup EXIT

publish() { docker tag "$1" "${REPO}:$2" && docker push -q "${REPO}:$2" >/dev/null && docker inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "${REPO}:$2" | grep "^${REPO}@"; }
version_running() { docker compose exec -T app cat /app/VERSION; }
password() { sed -n 's/^DB_MIGRATOR_PASSWORD=//p' .env; }
# Cache, queue and sessions are not part of it; the migrations account reads all.
data() { docker compose exec -T -e MYSQL_PWD="$(password)" db mariadb-dump -uprojektrack_migrator --single-transaction --skip-dump-date --skip-comments --no-tablespaces projektrack | sha256sum; }
files() { docker compose exec -T app sh -c 'cd /app/storage && find app -type f -exec sha256sum {} + | sort' | sha256sum; }
tables() { docker compose exec -T -e MYSQL_PWD="$(password)" db mariadb -uprojektrack_migrator -N projektrack -e "SHOW TABLES LIKE '$1'"; }
online() { [[ "$(curl -sk --resolve "${DOMAIN}:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://${DOMAIN}/login")" == 200 ]]; }
eventually_online() { for _ in $(seq 1 30); do online && return 0; sleep 2; done; return 1; }
no_snapshot() { [[ -z "$(docker compose --profile update run --rm -T snapshot ls -A /snapshot)" ]]; }
running() { [[ "$(docker compose ps --format '{{.State}}' "$1")" == running ]]; }
fails() { ! "$@" >"${work}/out" 2>&1; }
said() { grep -q "$1" "${work}/out"; }

say 'Images: 0.9.1, 0.9.2, and two broken ones'
docker run -d --name ci-registry -p 127.0.0.1:5000:5000 registry:2 >/dev/null
docker build -q -f Dockerfile --build-arg PROJEKTRACK_VERSION=0.9.1 -t projektrack:v1 ../.. >/dev/null
derive() { printf 'FROM projektrack:v1\nUSER root\nRUN echo %s > /app/VERSION%s\nUSER www-data\n' "$2" "${3:-}" | docker build -q -t "$1" - >/dev/null; }
derive projektrack:v2 0.9.2
cat > "${work}/migration.php" <<'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ci_broken', fn ($table) => $table->id());
        Schema::drop('shared_link_accesses');
        throw new RuntimeException('forced failure');
    }
};
EOF
printf 'FROM projektrack:v1\nUSER root\nRUN echo 0.9.3 > /app/VERSION\nCOPY migration.php /app/database/migrations/2099_01_01_000000_ci_broken.php\nUSER www-data\n' > "${work}/Dockerfile"
docker build -q -t projektrack:broken-migration "${work}" >/dev/null
derive projektrack:broken-health 0.9.4 ' \&\& sed -i "s/listen 8080/listen 8081/" /etc/nginx/nginx.conf'
v1="$(publish projektrack:v1 v1)"
v2="$(publish projektrack:v2 v2)"
bad_migration="$(publish projektrack:broken-migration v3)"
bad_health="$(publish projektrack:broken-health v4)"

mkdir "${work}/bin"
printf '#!/bin/sh\n[ "$1" = verify ] && [ ! -e "%s/unsigned" ]\n' "${work}" > "${work}/bin/cosign"
chmod +x "${work}/bin/cosign"
export PATH="${work}/bin:${PATH}"

say 'Start 0.9.1'
./projektrack install --domain "${DOMAIN}" --email ops@example.test --image "${v1}" >/dev/null
printf 'CADDY_GLOBAL_OPTIONS=local_certs\nPROJEKTRACK_REPOSITORY=%s\n' "${REPO}" >> .env
docker compose up -d
# Data to compare; the tenant host must answer for the sign-in page.
docker compose exec -T app php artisan tinker --execute="app(App\Tenancy\TenantContext::class)->run(App\Models\Tenant::query()->create(['slug' => 'ci', 'name' => 'CI d.o.o.', 'status' => 'active', 'country_code' => 'BA', 'default_locale' => 'bs', 'base_currency' => 'BAM', 'timezone' => 'Europe/Sarajevo', 'region' => 'eu', 'edition' => 'community']), fn () => App\Models\TenantDomain::query()->create(['host' => '${DOMAIN}', 'kind' => 'custom', 'is_primary' => true, 'verified_at' => now()]));"
docker compose exec -T app sh -c 'printf "keep me\n" > storage/app/private/update.txt'
check 'the instance is online' eventually_online
check 'a backup is written by the CLI' ./projektrack backup

say 'Refused updates change nothing'
touch "${work}/unsigned"
check 'unsigned image' fails ./projektrack update "${v2}"
check 'it says why' said 'not signed by the release workflow'
rm "${work}/unsigned"
check 'image by tag' fails ./projektrack update "${REPO}:v2"
check 'image of another repository' fails ./projektrack update "127.0.0.1:5000/other@${v2##*@}"
check 'same version' fails ./projektrack update "${v1}"
check 'it says why' said 'not newer'
check 'still 0.9.1' test "$(version_running)" = 0.9.1

say 'Update to 0.9.2'
check 'update succeeds' ./projektrack update "${v2}"
check '0.9.2 runs' test "$(version_running)" = 0.9.2
check '.env names the new image' grep -qx "PROJEKTRACK_IMAGE=${v2}" .env
check 'online again' eventually_online
for service in proxy app worker scheduler backup db redis; do check "${service} runs" running "${service}"; done
check 'the snapshot is gone' no_snapshot
check 'files are kept' docker compose exec -T app grep -q 'keep me' storage/app/private/update.txt
check 'older version is refused' fails ./projektrack update "${v1}"

for broken in migration health; do
    say "Forced failure: ${broken}"
    if [[ "${broken}" == migration ]]; then image="${bad_migration}"; else image="${bad_health}"; fi
    # Sign-in pages write sessions; with the proxy up nothing else writes.
    docker compose stop proxy scheduler >/dev/null 2>&1
    before_data="$(data)"
    before_files="$(files)"
    check 'update fails' fails ./projektrack update "${image}"
    cat "${work}/out"
    check 'it rolled back' said 'previous version runs again'
    docker compose stop proxy scheduler >/dev/null 2>&1
    check '0.9.2 runs' test "$(version_running)" = 0.9.2
    check '.env names the previous image' grep -qx "PROJEKTRACK_IMAGE=${v2}" .env
    check 'database is the same' test "$(data)" = "${before_data}"
    check 'files are the same' test "$(files)" = "${before_files}"
    check 'no table of the failed update' test -z "$(tables ci_broken)"
    check 'the snapshot is gone' no_snapshot
    docker compose up -d >/dev/null 2>&1
    check 'online again' eventually_online
done
check 'an update still works after a rollback' bash -c '! ./projektrack update "$1" 2>&1 | grep -q "snapshot of an earlier update"' _ "${bad_migration}"

say "Result: ${failures} failed"
[[ ${failures} -eq 0 ]]
