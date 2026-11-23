#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Approves a release (S-0924): a maintainer signs what the release workflow
# built, on their own machine. The private key is never on GitHub, so signing
# is the approval.
#
#   approve.sh vX.Y.Z <reviewed commit> <commit of the previous release, or "first"> <private key, PEM>
#
# Keep a copy of this file and verify.sh next to the key and run that copy from
# inside a clone: whoever can write to the repository could change the files
# there. The two full commit ids come from your own notes of the review, not
# from GitHub at the time of approval; tags can be set by anyone with write
# access and prove nothing.
#
# Needs git, gh (signed in) and OpenSSL 3. Nothing is signed or published
# before the last question is answered.
set -Eeuo pipefail

here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
tag="${1:-}"
commit="${2:-}"
previous="${3:-}"
key="${4:-}"
fail() { echo "approve: $*" >&2; exit 1; }
# Commit titles are written by others; they must not control the terminal.
plain() { LC_ALL=C tr -cd '[:print:]\n'; }

[[ "${tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ && "${commit}" =~ ^[0-9a-f]{40}$ \
    && "${previous}" =~ ^([0-9a-f]{40}|first)$ && -f "${key}" ]] \
    || fail 'Usage: approve.sh vX.Y.Z <reviewed commit> <commit of the previous release, or "first"> <private key, PEM>'
version="${tag#v}"
key="$(realpath "${key}")"
cd "$(git rev-parse --show-toplevel)"
repo=PROJEKTRA/projektrack

umask 077
work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT

tag_commit() { git ls-remote origin "refs/tags/${tag}" "refs/tags/${tag}^{}" | tail -n 1 | cut -f1; }

git fetch -q --no-tags origin main
[[ "$(tag_commit)" == "${commit}" ]] || fail "${tag} does not point to the reviewed commit."
git merge-base --is-ancestor "${commit}" FETCH_HEAD || fail "${commit} is not on main."
if [[ "${previous}" != 'first' ]]; then
    [[ "${previous}" != "${commit}" ]] && git merge-base --is-ancestor "${previous}" "${commit}" \
        || fail 'The previous release is not an ancestor of this one.'
fi

ci="$(gh run list --repo "${repo}" --workflow ci.yml --branch main --event push --commit "${commit}" --json conclusion --jq '[.[] | select(.conclusion == "success")] | length')"
[[ "${ci}" -ge 1 ]] || fail "CI on main is not green for ${commit}."

# The artifact of a run of the release workflow at exactly this commit.
run="$(gh run list --repo "${repo}" --workflow release.yml --event push --branch "${tag}" --commit "${commit}" --status success --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
[[ "${run}" =~ ^[0-9]+$ ]] || fail "No successful release build of ${tag} at ${commit}."
gh run download "${run}" --repo "${repo}" --name release-bundle --dir "${work}/dist"

dist="${work}/dist"
name="projektrack-${version}"
for file in zip cdx.json image.cdx.json image.txt; do
    [[ -s "${dist}/${name}.${file}" ]] || fail "The build has no ${name}.${file}."
done
[[ "$(cat "${dist}/${name}.image.txt")" =~ ^ghcr\.io/projektra/projektrack@sha256:[0-9a-f]{64}$ ]] || fail 'The build names no image of the project.'
named="$(sed 's/^[0-9a-f]\{64\} [ *]//' "${dist}/SHA256SUMS" | LC_ALL=C sort)"
there="$(cd "${dist}" && for file in *; do [[ "${file}" == 'SHA256SUMS' ]] || printf '%s\n' "${file}"; done | LC_ALL=C sort)"
[[ "${named}" == "${there}" ]] || fail 'The checksums do not name exactly the files of the build.'
(cd "${dist}" && sha256sum --strict -c SHA256SUMS >/dev/null) || fail 'The files do not match the checksums.'

# An update is checked with the keys of the running release, so both releases
# must trust this key.
public="$(openssl pkey -in "${key}" -pubout -outform DER | tail -c 32 | base64)"
trusts() { git show "$1:resources/release/public-keys.txt" 2>/dev/null | tr -d '\r' | grep -qxF "${public}"; }
trusts "${commit}" || fail 'This release does not ship the public key of this private key.'
if [[ "${previous}" != 'first' ]]; then
    trusts "${previous}" || fail 'The previous release does not trust this key; its instances could not update.'
fi

echo "Release:   ${tag}"
echo "Commit:    ${commit}"
echo "Previous:  ${previous}"
echo "Build:     https://github.com/${repo}/actions/runs/${run}"
echo "Image:     $(cat "${dist}/${name}.image.txt")"
if [[ "${previous}" != 'first' ]]; then
    echo 'Changes:'
    git --no-pager log --format='%h %an: %s' "${previous}..${commit}" | plain
    git --no-pager diff --text --no-ext-diff --no-textconv --stat=100 "${previous}" "${commit}" | plain
    echo 'What decides about trust, in full:'
    # As text whatever .gitattributes says, or a commit could hide a new key.
    git --no-pager diff --text --no-ext-diff --no-textconv --no-color "${previous}" "${commit}" -- resources/release deploy .github | plain
fi
read -r -p "Sign and publish ${tag}? Type ${tag} to approve: " answer
[[ "${answer}" == "${tag}" ]] || fail 'Not approved. Nothing was signed or published.'

openssl pkeyutl -sign -inkey "${key}" -rawin -in "${dist}/SHA256SUMS" -out "${dist}/SHA256SUMS.sig"
printf '%s\n' "${public}" > "${work}/keys"
"${here}/verify.sh" "${dist}" "${work}/keys" >/dev/null || fail 'The signature does not verify.'
[[ "$(tag_commit)" == "${commit}" ]] || fail "${tag} was moved in the meantime. Nothing was published."

gh release create "${tag}" --repo "${repo}" --verify-tag --title "Projektrack ${version}" \
    --notes 'Verify before installing or updating: see docs/admin/releases.md. The source archives below are not signed.' "${dist}/"*
