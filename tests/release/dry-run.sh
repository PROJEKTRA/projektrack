#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI dry run of the release pipeline (S-0924): build the ZIP as the release
# workflow does, sign it with a throwaway Ed25519 key, and check that the
# verification accepts it and refuses it after any change. Runs from the
# repository root after "composer install".
set -Eeuo pipefail

failures=0
ok() { printf 'ok    %s\n' "$*"; }
bad() { printf 'FAIL  %s\n' "$*"; failures=$((failures + 1)); }
check() { local name="$1"; shift; if "$@"; then ok "${name}"; else bad "${name}"; fi; }

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
dist="${work}/dist"
version=0.0.0-ci

deploy/release/build-zip.sh "${version}" "${dist}" >/dev/null
zip="${dist}/projektrack-${version}.zip"
listing="$(unzip -Z1 "${zip}")"
has() { grep -qx "projektrack-${version}/$1" <<<"${listing}"; }
lacks() { ! grep -q "^projektrack-${version}/$1" <<<"${listing}"; }

check 'ZIP has vendor/' has 'vendor/autoload.php'
check 'ZIP has the compiled assets' has 'public/build/manifest.json'
check 'ZIP names its version' bash -c '[[ "$(unzip -p "$1" "projektrack-$2/VERSION")" == "$2" ]]' _ "${zip}" "${version}"
check 'ZIP has the shipped release keys' has 'resources/release/public-keys.txt'
check 'ZIP has no .env' lacks '.env$'
check 'ZIP has no tests' lacks 'tests/'
check 'ZIP has no node_modules' lacks 'node_modules/'
check 'ZIP has no dev packages' lacks 'vendor/pestphp/'
check 'ZIP has no repository data' lacks '.git/'

# Throwaway key pair; the public key as the verification expects it.
openssl genpkey -algorithm ed25519 -out "${work}/key.pem" 2>/dev/null
public="$(openssl pkey -in "${work}/key.pem" -pubout -outform DER | tail -c 32 | base64)"
deploy/release/sign.sh "${dist}" "${work}/key.pem" >/dev/null

# Trusted keys can only come from the shipped file: the checks run the
# unpacked release itself, with the throwaway key written into its file.
app="${work}/app"
mkdir "${app}"
unzip -q "${zip}" -d "${app}"
app="${app}/projektrack-${version}"
cp "${app}/.env.example" "${app}/.env"
php "${app}/artisan" key:generate --no-interaction >/dev/null
keys_file="${app}/resources/release/public-keys.txt"
accepts() { printf '%s\n' "$1" > "${keys_file}"; php "${app}/artisan" projektrack:release:verify "$2" "${@:3}" >/dev/null 2>&1; }
refuses() { ! accepts "$@"; }

check 'a genuine release is accepted' accepts "${public}" "${zip}" --release="${version}"
check 'another expected release is refused' refuses "${public}" "${zip}" --release=0.0.1

other="$(openssl genpkey -algorithm ed25519 2>/dev/null | openssl pkey -pubout -outform DER | tail -c 32 | base64)"
check 'another key is refused' refuses "${other}" "${zip}"
check 'no shipped key refuses' refuses '# no key' "${zip}"
check 'an environment variable cannot add a key' bash -c '! RELEASE_PUBLIC_KEYS="$1" APP_ENV=testing php artisan projektrack:release:verify "$2" >/dev/null 2>&1' _ "${public}" "${zip}"

cp "${zip}" "${work}/original.zip"
printf 'tampered' >> "${zip}"
check 'a changed ZIP is refused' refuses "${public}" "${zip}"

cp "${work}/original.zip" "${zip}"
check 'the restored ZIP is accepted again' accepts "${public}" "${zip}"
# The ZIP's line stays correct; only an added line changes the signed list.
printf '%064d  extra.zip\n' 0 >> "${dist}/SHA256SUMS"
check 'a changed checksum list is refused' refuses "${public}" "${zip}"

# The same folder with OpenSSL only, as the offline approval checks it.
deploy/release/sign.sh "${dist}" "${work}/key.pem" >/dev/null
printf '# comment\n%s\n' "${public}" > "${work}/keys"
openssl_verify() { deploy/release/verify.sh "${dist}" "$1" >/dev/null 2>&1; }
openssl_refuses() { ! openssl_verify "$1"; }
check 'OpenSSL accepts the genuine folder' openssl_verify "${work}/keys"
printf '%s\n' "${other}" > "${work}/other"
check 'OpenSSL refuses another key' openssl_refuses "${work}/other"
printf '# none\n' > "${work}/none"
check 'OpenSSL refuses without keys' openssl_refuses "${work}/none"
printf 'x' >> "${zip}"
check 'OpenSSL refuses a changed file' openssl_refuses "${work}/keys"

printf '\nResult: %s failed\n' "${failures}"
[[ ${failures} -eq 0 ]]
