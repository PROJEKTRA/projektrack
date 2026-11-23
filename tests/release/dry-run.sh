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

# The key override counts only with APP_ENV=testing.
accepts() { APP_ENV=testing RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" "${@:3}" >/dev/null 2>&1; }
refuses() { ! accepts "$@"; }

check 'a genuine release is accepted' accepts "${public}" "${zip}" --release="${version}"
check 'another expected release is refused' refuses "${public}" "${zip}" --release=0.0.1

other="$(openssl genpkey -algorithm ed25519 2>/dev/null | openssl pkey -pubout -outform DER | tail -c 32 | base64)"
check 'another key is refused' refuses "${other}" "${zip}"
check 'no configured key refuses' refuses '' "${zip}"
check 'outside tests the key override is ignored' bash -c '! APP_ENV=production RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" >/dev/null 2>&1' _ "${public}" "${zip}"

cp "${zip}" "${work}/original.zip"
printf 'tampered' >> "${zip}"
check 'a changed ZIP is refused' refuses "${public}" "${zip}"

cp "${work}/original.zip" "${zip}"
check 'the restored ZIP is accepted again' accepts "${public}" "${zip}"
# The ZIP's line stays correct; only an added line changes the signed list.
printf '%064d  extra.zip\n' 0 >> "${dist}/SHA256SUMS"
check 'a changed checksum list is refused' refuses "${public}" "${zip}"

printf '\nResult: %s failed\n' "${failures}"
[[ ${failures} -eq 0 ]]
