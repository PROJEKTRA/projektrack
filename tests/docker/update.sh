#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI test of the operations CLI (S-0927): an update, refused updates, and two
# forced failures (migration, health) that must roll back to the same data and
# the previous version. Images come from a local registry; the running image
# trusts a throwaway release key, as a release trusts the real one.
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
    rm -rf .env .lock .update-state backup-identity.txt backups "${work}"
}
trap cleanup EXIT

publish() { docker tag "$1" "${REPO}:$2" && docker push -q "${REPO}:$2" >/dev/null && docker inspect --format '{{range .RepoDigests}}{{println .}}{{end}}' "${REPO}:$2" | grep "^${REPO}@"; }
version_running() { docker compose exec -T app cat /app/VERSION; }
password() { sed -n 's/^DB_MIGRATOR_PASSWORD=//p' .env; }
# Without the permission catalog: every start writes its timestamps anew.
data() { docker compose exec -T -e MYSQL_PWD="$(password)" db mariadb-dump -uprojektrack_migrator --single-transaction --skip-dump-date --skip-comments --no-tablespaces --ignore-table=projektrack.permissions projektrack | sha256sum; }
files() { docker compose exec -T app sh -c 'cd /app/storage && find app -type f -exec sha256sum {} + | sort' | sha256sum; }
tables() { docker compose exec -T -e MYSQL_PWD="$(password)" db mariadb -uprojektrack_migrator -N projektrack -e "SHOW TABLES LIKE '$1'"; }
online() { [[ "$(curl -sk --resolve "${DOMAIN}:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://${DOMAIN}/login")" == 200 ]]; }
eventually_online() { for _ in $(seq 1 30); do online && return 0; sleep 2; done; return 1; }
no_snapshot() { [[ -z "$(docker compose --profile update run --rm -T snapshot ls -A /snapshot)" ]]; }
running() { [[ "$(docker compose ps --format '{{.State}}' "$1")" == running ]]; }
fails() { ! "$@" >"${work}/out" 2>&1; }
fails_wait() { ! wait "$1"; }
said() { grep -q "$1" "${work}/out"; }

say 'Images: 0.9.1, 0.9.2, and two broken ones'
docker run -d --name ci-registry -p 127.0.0.1:5000:5000 registry:2 >/dev/null
docker build -q -f Dockerfile --build-arg PROJEKTRACK_VERSION=0.9.1 -t projektrack:v0 ../.. >/dev/null
openssl genpkey -algorithm ed25519 -out "${work}/key.pem" 2>/dev/null
openssl genpkey -algorithm ed25519 -out "${work}/foreign.pem" 2>/dev/null
public="$(openssl pkey -in "${work}/key.pem" -pubout -outform DER | tail -c 32 | base64)"
printf 'FROM projektrack:v0\nUSER root\nRUN echo "%s" > /app/resources/release/public-keys.txt\nUSER www-data\n' "${public}" | docker build -q -t projektrack:v1 - >/dev/null
derive() { printf 'FROM projektrack:v1\nUSER root\nRUN echo %s > /app/VERSION%s\nUSER www-data\n' "$2" "${3:-}" | docker build -q -t "$1" - >/dev/null; }
derive projektrack:v2 0.9.2
derive projektrack:v5 0.9.5
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
        unlink('/app/storage/app/private/update.txt');
        file_put_contents('/app/storage/app/private/broken.txt', 'x');
        // Time for the test to close the terminal during the migration.
        if (file_exists('/app/storage/app/private/slow.txt')) {
            sleep(60);
        }
        throw new RuntimeException('forced failure');
    }
};
EOF
printf 'FROM projektrack:v1\nUSER root\nRUN echo 0.9.3 > /app/VERSION\nCOPY migration.php /app/database/migrations/2099_01_01_000000_ci_broken.php\nUSER www-data\n' > "${work}/Dockerfile"
docker build -q -t projektrack:broken-migration "${work}" >/dev/null
derive projektrack:broken-health 0.9.4 ' && sed -i "s/listen 8080/listen 8081/" /etc/nginx/nginx.conf'
check 'the broken image listens elsewhere' docker run --rm --entrypoint grep projektrack:broken-health -q 'listen 8081' /etc/nginx/nginx.conf
v1="$(publish projektrack:v1 v1)"
v2="$(publish projektrack:v2 v2)"
v5="$(publish projektrack:v5 v5)"
bad_migration="$(publish projektrack:broken-migration v3)"
bad_health="$(publish projektrack:broken-health v4)"

# release <folder> <version> <image> [key]: the signed files of a release.
release() {
    mkdir "${work}/$1"
    printf '%s\n' "$3" > "${work}/$1/projektrack-$2.image.txt"
    ../release/sign.sh "${work}/$1" "${4:-${work}/key.pem}" >/dev/null
}
release r1 0.9.1 "${v1}"
release r2 0.9.2 "${v2}"
release r5 0.9.5 "${v5}"
release bad-migration 0.9.3 "${bad_migration}"
release bad-health 0.9.4 "${bad_health}"
release foreign 0.9.2 "${v2}" "${work}/foreign.pem"
release renamed 0.9.9 "${v2}"
release other-repository 0.9.2 "127.0.0.1:5000/other@${v2##*@}"
release by-tag 0.9.2 "${REPO}:v2"
cp -r "${work}/r2" "${work}/changed"
printf '%s\n' "${bad_health}" > "${work}/changed/projektrack-0.9.2.image.txt"
say 'Start 0.9.1'
./projektrack install --domain "${DOMAIN}" --email ops@example.test --image "${v1}" >/dev/null
printf 'CADDY_GLOBAL_OPTIONS=local_certs\nPROJEKTRACK_REPOSITORY=%s\n' "${REPO}" >> .env
docker compose up -d
# Data to compare; the tenant host must answer for the sign-in page.
docker compose exec -T -e HOME=/tmp -e XDG_CONFIG_HOME=/tmp/config app php artisan tinker --execute="app(App\Tenancy\TenantContext::class)->run(App\Models\Tenant::query()->create(['slug' => 'ci', 'name' => 'CI d.o.o.', 'status' => 'active', 'country_code' => 'BA', 'default_locale' => 'bs', 'base_currency' => 'BAM', 'timezone' => 'Europe/Sarajevo', 'region' => 'eu', 'edition' => 'community']), fn () => App\Models\TenantDomain::query()->create(['host' => '${DOMAIN}', 'kind' => 'custom', 'is_primary' => true, 'verified_at' => now()]));"
docker compose exec -T app sh -c 'printf "keep me\n" > storage/app/private/update.txt'
check 'the instance is online' eventually_online
check 'a backup is written by the CLI' ./projektrack backup

say 'Refused updates change nothing'
check 'signed by another key' fails ./projektrack update "${work}/foreign"
check 'it says why' said 'not signed by a trusted'
check 'changed after signing' fails ./projektrack update "${work}/changed"
check 'it says why' said 'was changed after signing'
check 'renamed to another version' fails ./projektrack update "${work}/renamed"
check 'it says why' said 'the release names 0.9.9'
check 'image of another repository' fails ./projektrack update "${work}/other-repository"
check 'it says why' said 'names no image of'
check 'image by tag' fails ./projektrack update "${work}/by-tag"
check 'it says why' said 'names no image of'
check 'no folder' fails ./projektrack update "${v2}"
check 'same version' fails ./projektrack update "${work}/r1"
check 'it says why' said 'not newer'
check 'still 0.9.1' test "$(version_running)" = 0.9.1

backups() { docker run --rm --user 0:0 --network none --entrypoint sh -v "$PWD/backups:/backups:ro" projektrack:v1 -c 'ls -d /backups/[0-9]*Z | wc -l'; }
quiet() { docker compose stop proxy scheduler worker >/dev/null 2>&1; }

say 'A snapshot left by an earlier update is never touched'
docker compose --profile update run --rm -T snapshot projektrack-snapshot create
before="$(docker compose --profile update run --rm -T snapshot sh -c 'cat /snapshot/SHA256SUMS')"
check 'update refuses' fails ./projektrack update "${work}/r2"
check 'it says why' said 'snapshot of an earlier update exists'
check 'still 0.9.1 and online' eventually_online
check 'the snapshot is unchanged' test "$(docker compose --profile update run --rm -T snapshot sh -c 'cat /snapshot/SHA256SUMS')" = "${before}"
docker compose --profile update run --rm -T snapshot projektrack-snapshot drop
touch .update-state
check 'an unfinished update refuses another one' fails ./projektrack update "${work}/r2"
check 'it says why' said 'did not finish'
rm .update-state

say 'Update to 0.9.2'
count="$(backups)"
# Watches from inside for the maintenance page while the update runs.
./projektrack update "${work}/r2" > "${work}/update.log" 2>&1 &
update=$!
maintenance=0
while kill -0 "${update}" 2>/dev/null; do
    [[ "$(curl -sk --resolve "${DOMAIN}:443:127.0.0.1" -o /dev/null -w '%{http_code}' "https://${DOMAIN}/login")" == 503 ]] && maintenance=1
    sleep 0.3
done
check 'update succeeds' wait "${update}"
cat "${work}/update.log"
check 'visitors saw the maintenance page' test "${maintenance}" = 1
check 'a backup was taken before' test "$(backups)" -gt "${count}"
check '0.9.2 runs' test "$(version_running)" = 0.9.2
check '.env names the new image' grep -qx "PROJEKTRACK_IMAGE=${v2}" .env
check 'online again' eventually_online
for service in proxy app worker scheduler backup db redis; do check "${service} runs" running "${service}"; done
check 'the snapshot is gone' no_snapshot
check 'no state file is left' test ! -e .update-state
check 'files are kept' docker compose exec -T app grep -q 'keep me' storage/app/private/update.txt
check 'older version is refused' fails ./projektrack update "${work}/r1"
check 'it says why' said 'not newer'

after_rollback() {
    check 'it rolled back' said 'previous version runs again'
    quiet
    check '0.9.2 runs' test "$(version_running)" = 0.9.2
    check '.env names the previous image' grep -qx "PROJEKTRACK_IMAGE=${v2}" .env
    check 'database is the same' test "$(data)" = "${before_data}"
    check 'files are the same' test "$(files)" = "${before_files}"
    check 'no table of the failed update' test -z "$(tables ci_broken)"
    check 'the snapshot is gone' no_snapshot
    check 'no state file is left' test ! -e .update-state
    check 'no one-off container is left' test -z "$(docker ps -aq --filter name=projektrack-update-)"
    docker compose up -d >/dev/null 2>&1
    check 'online again' eventually_online
}

for broken in migration health; do
    say "Forced failure: ${broken}"
    if [[ "${broken}" == migration ]]; then image="${work}/bad-migration"; else image="${work}/bad-health"; fi
    quiet
    before_data="$(data)"
    before_files="$(files)"
    check 'update fails' fails ./projektrack update "${image}"
    cat "${work}/out"
    after_rollback
done

say 'The terminal closes twice during the migration'
docker compose exec -T app touch storage/app/private/slow.txt
quiet
before_data="$(data)"
before_files="$(files)"
setsid ./projektrack update "${work}/bad-migration" > "${work}/out" 2>&1 &
update=$!
for _ in $(seq 1 120); do
    [[ -n "$(tables ci_broken)" ]] && break
    sleep 1
done
check 'the migration is running' test -n "$(tables ci_broken)"
kill -HUP -- "-${update}"
sleep 2
kill -HUP -- "-${update}" 2>/dev/null || true
check 'update fails' fails_wait "${update}"
cat "${work}/out"
after_rollback

say 'A rollback that fails, and the recovery by hand of the guide'
quiet
before_data="$(data)"
before_files="$(files)"
setsid ./projektrack update "${work}/bad-migration" > "${work}/out" 2>&1 &
update=$!
for _ in $(seq 1 120); do
    [[ -n "$(tables ci_broken)" ]] && break
    sleep 1
done
check 'the migration is running' test -n "$(tables ci_broken)"
# Damage the snapshot; the good copy stands for a repaired cause.
docker compose --profile update run --rm -T snapshot sh -c 'cp /snapshot/database.sql.gz /snapshot/good && echo x >> /snapshot/database.sql.gz'
kill -HUP -- "-${update}"
status=0
wait "${update}" || status=$?
cat "${work}/out"
check 'exit code 2' test "${status}" = 2
check 'it says so' said 'ROLLBACK FAILED'
check 'the proxy stays closed' test -z "$(docker compose ps --status running -q proxy)"
check 'a damaged snapshot changes nothing' test -n "$(tables ci_broken)"
check 'the state names the previous image' grep -qx "previous=${v2}" .update-state
check 'a new update is refused' fails ./projektrack update "${work}/r5"
docker compose --profile update run --rm -T snapshot sh -c 'mv /snapshot/good /snapshot/database.sql.gz'
docker compose stop proxy app worker scheduler backup >/dev/null 2>&1
sed -i "s|^PROJEKTRACK_IMAGE=.*|PROJEKTRACK_IMAGE=$(sed -n 's/^previous=//p' .update-state)|" .env
docker compose --profile update run --rm -T snapshot projektrack-snapshot restore
docker compose up -d --no-deps app >/dev/null 2>&1
for _ in $(seq 1 30); do docker compose exec -T app php artisan up >/dev/null 2>&1 && break; sleep 2; done
check 'healthy behind the closed proxy' test "$(docker compose exec -T app wget -q -O - http://127.0.0.1:8080/health)" = OK
check 'database is the same' test "$(data)" = "${before_data}"
check 'files are the same' test "$(files)" = "${before_files}"
docker compose --profile update run --rm -T snapshot projektrack-snapshot drop
rm .update-state
check 'the snapshot is gone' no_snapshot
docker compose up -d >/dev/null 2>&1
check 'online again' eventually_online
docker compose exec -T app rm storage/app/private/slow.txt

say 'An update still works after rollbacks'
check 'update succeeds' ./projektrack update "${work}/r5"
check '0.9.5 runs' test "$(version_running)" = 0.9.5
check 'online again' eventually_online

say 'Restore with the CLI on an empty instance'
stamp="$(docker run --rm --user 0:0 --network none --entrypoint sh -v "$PWD/backups:/backups:ro" projektrack:v1 -c 'ls /backups' | grep -E '^[0-9]{8}T[0-9]{6}Z$' | tail -n 1)"
docker compose --profile update down -v >/dev/null 2>&1
check 'restore succeeds' ./projektrack restore "${stamp}"
check 'online again' eventually_online
check 'files are back' docker compose exec -T app grep -q 'keep me' storage/app/private/update.txt

say "Result: ${failures} failed"
[[ ${failures} -eq 0 ]]
