#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Verifies a release folder with OpenSSL only (S-0924): SHA256SUMS carries a
# signature of one of the given keys, and every file it names is unchanged.
#
#   deploy/release/verify.sh <release folder> <file with base64 public keys>
set -Eeuo pipefail

dir="${1:-}"
keys="${2:-}"
[[ -d "${dir}" && -f "${keys}" ]] || { echo 'Usage: verify.sh <release folder> <file with public keys>' >&2; exit 2; }

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

signed=0
while IFS= read -r key; do
    [[ -z "${key}" || "${key}" == '#'* ]] && continue
    # The fixed DER prefix of an Ed25519 public key, then its 32 bytes.
    { printf '\060\052\060\005\006\003\053\145\160\003\041\000'; printf '%s' "${key}" | base64 -d; } > "${work}/key.der"
    if openssl pkeyutl -verify -pubin -keyform DER -inkey "${work}/key.der" -rawin \
        -in "${dir}/SHA256SUMS" -sigfile "${dir}/SHA256SUMS.sig" >/dev/null 2>&1; then
        signed=1
    fi
done < <(tr -d '\r' < "${keys}")

[[ "${signed}" == 1 ]] || { echo 'SHA256SUMS is not signed by one of these keys.' >&2; exit 1; }
(cd "${dir}" && sha256sum --strict -c SHA256SUMS)
