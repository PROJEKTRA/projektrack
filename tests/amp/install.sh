#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI test of plain hosting (S-0925): the release ZIP on Apache with PHP and
# MariaDB, without Composer, Node, Redis or a queue worker. Installs as an
# operator would, checks pages, headers, protection and the cron entry, and
# runs the installation again. Usage: tests/amp/install.sh <folder for the ZIP>
set -Eeuo pipefail

CURL_HOME="$(mktemp -d)"
export CURL_HOME
printf 'resolve = "test.localhost:8080:127.0.0.1"\nresolve = "test.localhost:8081:127.0.0.1"\npath-as-is\n' > "${CURL_HOME}/.curlrc"

out="${1:?folder for the ZIP}"
mkdir -p "${out}"
out="$(cd "${out}" && pwd)"
failures=0
say() { printf '\n== %s\n' "$*"; }
check() { local name="$1"; shift; if "$@"; then printf 'ok    %s\n' "${name}"; else printf 'FAIL  %s\n' "${name}"; failures=$((failures + 1)); fi; }

cleanup() {
    status=$?
    if [[ ${status} -ne 0 || ${failures} -ne 0 ]]; then
        docker logs --tail 60 amp-web 2>&1 || true
        docker exec amp-web sh -c 'tail -n 60 /srv/site/storage/logs/*.log' 2>/dev/null || true
    fi
    docker rm -f amp-web amp-db >/dev/null 2>&1 || true
    docker network rm amp >/dev/null 2>&1 || true
}
trap cleanup EXIT

say 'Build the ZIP'
deploy/release/build-zip.sh 0.0.0-test "${out}" >/dev/null
zip="${out}/projektrack-0.0.0-test.zip"

say 'Hosting: Apache with PHP, MariaDB, cron'
docker build -q -t amp-web - >/dev/null <<'EOF'
FROM php:8.4-apache
RUN apt-get update -qq && apt-get install -y -qq libicu-dev libpng-dev libzip-dev unzip >/dev/null \
 && docker-php-ext-install -j"$(nproc)" intl pdo_mysql bcmath gd zip >/dev/null \
 && a2enmod rewrite headers >/dev/null \
 && sed -i 's#/var/www/html#/srv/site/public#' /etc/apache2/sites-available/000-default.conf \
 && printf '<Directory /srv/>\nAllowOverride All\nRequire all granted\n</Directory>\nServerTokens Prod\nListen 81\n<VirtualHost *:81>\nDocumentRoot /srv/site\n</VirtualHost>\n' > /etc/apache2/conf-enabled/site.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && mkdir /srv/site
EOF
docker network create amp >/dev/null
docker run -d --name amp-db --network amp -e MARIADB_RANDOM_ROOT_PASSWORD=1 -e MARIADB_DATABASE=site -e MARIADB_USER=site -e MARIADB_PASSWORD=synthetic-password \
    --health-cmd 'healthcheck.sh --connect --innodb_initialized' --health-interval 3s --health-retries 40 mariadb:11.4 >/dev/null
docker run -d --name amp-web --network amp -p 127.0.0.1:8080:80 -p 127.0.0.1:8081:81 -v "${zip}:/tmp/site.zip:ro" amp-web >/dev/null
docker exec amp-web sh -c 'unzip -q /tmp/site.zip -d /tmp/x && cp -r /tmp/x/*/. /srv/site/ && chown -R www-data:www-data /srv/site'
for _ in $(seq 1 60); do [[ "$(docker inspect -f '{{.State.Health.Status}}' amp-db)" == healthy ]] && break; sleep 2; done

php_() { docker exec -u www-data -w /srv/site amp-web php artisan "$@"; }
code() { curl -s -o /dev/null -w '%{http_code}' "$@"; }
set_env() { docker exec -u www-data amp-web sed -i "s|^$1=.*|$1=$2|" /srv/site/.env; }
no_tool() { ! docker exec amp-web sh -c "command -v $1" >/dev/null 2>&1; }

for tool in composer node npm redis-server; do check "no ${tool} on the host" no_tool "${tool}"; done

say 'Install'
check 'first run writes .env' php_ projektrack:install
check '.env is readable by its owner only' test "$(docker exec amp-web stat -c %a /srv/site/.env)" = 600
check 'placeholders are refused' bash -c '! docker exec -u www-data -w /srv/site amp-web php artisan projektrack:install >/dev/null 2>&1'
set_env APP_URL http://test.localhost
set_env DB_CONNECTION mariadb
set_env DB_HOST amp-db
set_env DB_DATABASE site
set_env DB_USERNAME site
# A test installation without a mail server: the link lands in the log.
set_env APP_ENV staging
set_env MAIL_MAILER log
set_env LOG_LEVEL debug
set_env MAIL_FROM_ADDRESS noreply@example.test
set_env DB_PASSWORD wrong
check 'a wrong database password is refused without showing it' bash -c 'out="$(docker exec -u www-data -w /srv/site amp-web php artisan projektrack:install 2>&1)"; [[ $? -ne 0 && "$out" == *"does not answer"* && "$out" != *wrong* ]]'
set_env DB_PASSWORD synthetic-password
check 'second run installs' php_ projektrack:install --slug=test --name='Test d.o.o.' --admin-email=admin@example.test --admin-name='Test Admin'

say 'Cron'
link() { docker exec amp-web sh -c "grep -rhoE 'http://test.localhost/reset-password/[A-Za-z0-9]+' /srv/site/storage/logs | tail -n 1"; }
check 'no link before cron ran' test -z "$(link || true)"
docker exec -u www-data -w /srv/site amp-web php artisan schedule:run >/dev/null
check 'cron sent the setup link' test -n "$(link || true)"

say 'In the browser'
url=http://test.localhost:8080
headers="$(curl -s -o /tmp/login.html -D - "${url}/login")"
check 'sign-in page' grep -q '^HTTP/1.1 200' <<<"${headers}"
check 'in Bosnian' grep -q 'lang="bs"' /tmp/login.html
check 'noindex' grep -qi '^x-robots-tag: noindex' <<<"${headers}"
check 'no X-Powered-By' bash -c '! grep -qi "^x-powered-by" <<<"$1"' _ "${headers}"
asset="$(grep -oE '/build/assets/[A-Za-z0-9_-]+\.css' /tmp/login.html | head -n 1)"
check 'stylesheet' test "$(code "${url}${asset}")" = 200
check 'setup page of the link' test "$(code "${url}$(link | sed 's#http://test.localhost##')")" = 200
check 'health' test "$(curl -s "${url}/health")" = OK
check 'robots.txt' bash -c 'curl -s "$1/robots.txt" | grep -q "^Disallow: /$"' _ "${url}"
check '/.env is not served' bash -c '[[ "$(curl -s -o /dev/null -w "%{http_code}" "$1/.env")" =~ ^40[34]$ ]]' _ "${url}"
wrong=http://test.localhost:8081
for path in / /.env /composer.json /artisan /storage/logs/ /config/app.php /vendor/autoload.php /deploy/amp/env.example /public/../.env; do
    check "web root on the wrong folder: ${path} is refused" bash -c '[[ "$(curl -s -o /dev/null -w "%{http_code}" "$1")" =~ ^40[34]$ ]]' _ "${wrong}${path}"
done
check 'the refusal is noindex' bash -c 'curl -s -o /dev/null -D - "$1/" | grep -qi "^x-robots-tag: noindex"' _ "${wrong}"

say 'Again, as for a newer ZIP'
check 'running it again keeps the data' bash -c 'docker exec -u www-data -w /srv/site amp-web php artisan projektrack:install | grep -q "data is kept"'
check 'still online' test "$(code "${url}/login")" = 200
check 'system check runs' bash -c 'docker exec -u www-data -w /srv/site amp-web php artisan projektrack:license:show | grep -q "^Instance: inst_"'

say "Result: ${failures} failed"
[[ ${failures} -eq 0 ]]
