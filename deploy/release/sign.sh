#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Writes SHA256SUMS over every file of a release folder and signs it with the
# Ed25519 release key (S-0924): SHA256SUMS.sig holds the raw 64-byte
# signature that projektrack:release:verify checks with sodium.
#
#   deploy/release/sign.sh <release folder> <private key, PEM>
#
# Used by the CI tests with throwaway keys; releases are signed by approve.sh.
set -Eeuo pipefail

dir="${1:-}"
key="${2:-}"
[[ -d "${dir}" && -f "${key}" ]] || { echo 'Usage: sign.sh <release folder> <private key, PEM>' >&2; exit 2; }

cd "${dir}"
rm -f SHA256SUMS SHA256SUMS.sig
find . -maxdepth 1 -type f ! -name 'SHA256SUMS*' -printf '%f\n' | LC_ALL=C sort | xargs -r sha256sum > SHA256SUMS
openssl pkeyutl -sign -inkey "${key}" -rawin -in SHA256SUMS -out SHA256SUMS.sig
[[ "$(stat -c %s SHA256SUMS.sig)" -eq 64 ]] || { echo 'The key is not an Ed25519 key.' >&2; exit 1; }
cat SHA256SUMS
