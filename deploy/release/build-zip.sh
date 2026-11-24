#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Builds the release ZIP (S-0924) from the committed files of HEAD only, with
# vendor/ (no dev packages) and the compiled assets, so a plain AMP host needs
# neither Composer nor Node. Needs PHP 8.4 with the extensions of
# infrastructure profiles 1.1, Composer, Node 24, git and zip.
#
#   deploy/release/build-zip.sh <version> <output folder>
set -Eeuo pipefail

version="${1:-}"
out="${2:-}"
[[ "${version}" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ ]] || { echo 'Usage: build-zip.sh <version, e.g. 0.9.0> <output folder>' >&2; exit 2; }
[[ -n "${out}" ]] || { echo 'Give an output folder.' >&2; exit 2; }

root="$(git rev-parse --show-toplevel)"
mkdir -p "${out}"
out="$(cd "${out}" && pwd)"
work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
name="projektrack-${version}"
if [[ "${VENDOR:-}" == 1 ]]; then name="projektrack-vendor-${version}"; fi
stage="${work}/${name}"
mkdir "${stage}"

# Committed files only: nothing local, ignored or excluded can slip in.
git -C "${root}" archive --format=tar HEAD | tar -x -C "${stage}"
cd "${stage}"
# The release is the Community edition. VENDOR=1 is the build for the licensor's own instance.
if [[ "${VENDOR:-}" == 1 ]]; then rm -rf ee/licensing; else rm -rf ee; fi

composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-progress
npm ci --no-audit --no-fund
# A throwaway key for the asset build only (route types run artisan).
cp .env.example .env
php artisan key:generate --no-interaction
npm run build
rm -f .env
rm -rf node_modules public/hot bootstrap/cache/strings
printf '%s\n' "${version}" > VERSION

# Development material is not part of a release.
rm -rf tests modules/*/tests ee/modules/*/tests docs .github scripts playwright*.config.ts .semgrep .gitleaks.toml phpunit.xml vitest.config.ts \
    eslint.config.js .prettierrc.json phpstan.neon pint.json .dockerignore .editorconfig
find storage -type f ! -name .gitignore -delete

cd "${work}"
rm -f "${out}/${name}.zip"
zip -q -r -X "${out}/${name}.zip" "${name}"
echo "${out}/${name}.zip"
