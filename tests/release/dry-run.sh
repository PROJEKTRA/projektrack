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

verify() { RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" >/dev/null 2>&1; }
check 'a genuine release is accepted' verify "${public}" "${zip}"

other="$(openssl genpkey -algorithm ed25519 2>/dev/null | openssl pkey -pubout -outform DER | tail -c 32 | base64)"
check 'another key is refused' bash -c '! RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" >/dev/null 2>&1' _ "${other}" "${zip}"
check 'no configured key refuses' bash -c '! RELEASE_PUBLIC_KEYS= php artisan projektrack:release:verify "$1" >/dev/null 2>&1' _ "${zip}"

cp "${zip}" "${work}/original.zip"
printf 'tampered' >> "${zip}"
check 'a changed ZIP is refused' bash -c '! RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" >/dev/null 2>&1' _ "${public}" "${zip}"

cp "${work}/original.zip" "${zip}"
sha256sum "${zip}" | sed "s#${dist}/##" > "${dist}/SHA256SUMS"
check 'a changed checksum list is refused' bash -c '! RELEASE_PUBLIC_KEYS="$1" php artisan projektrack:release:verify "$2" >/dev/null 2>&1' _ "${public}" "${zip}"

printf '\nResult: %s failed\n' "${failures}"
[[ ${failures} -eq 0 ]]
