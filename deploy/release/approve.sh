#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# Approves a release offline (S-0924): a maintainer signs what the release
# workflow built, on their own machine. The private key never leaves it, so
# signing is the approval. Run from a clean, current clone that you trust.
#
#   deploy/release/approve.sh vX.Y.Z <private key, PEM>
#
# Needs git, gh (signed in), openssl 3 and the key. It refuses a tag that is not
# on main, a build that did not come from the release workflow of exactly that
# commit, changed files, and a key that this or the previous release does not
# trust. Nothing is published before the last question is answered.
set -Eeuo pipefail

tag="${1:-}"
key="${2:-}"
fail() { echo "approve: $*" >&2; exit 1; }
[[ "${tag}" =~ ^v[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.]+)?$ && -f "${key}" ]] || fail 'Usage: approve.sh vX.Y.Z <private key, PEM>'
version="${tag#v}"
key="$(realpath "${key}")"
cd "$(git rev-parse --show-toplevel)"
repo=PROJEKTRA/projektrack

work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
umask 077

git fetch -q origin main "refs/tags/${tag}:refs/tags/${tag}"
commit="$(git rev-parse "${tag}^{commit}")"
git merge-base --is-ancestor "${commit}" origin/main || fail "${tag} is not on main."

ci="$(gh run list --repo "${repo}" --workflow ci.yml --branch main --event push --commit "${commit}" --json conclusion --jq '[.[] | select(.conclusion == "success")] | length')"
[[ "${ci}" -ge 1 ]] || fail "CI on main is not green for ${commit}."

# The files of the run itself: its artifact cannot be replaced afterwards.
run="$(gh run list --repo "${repo}" --workflow release.yml --event push --branch "${tag}" --commit "${commit}" --status success --limit 1 --json databaseId --jq '.[0].databaseId // empty')"
[[ -n "${run}" ]] || fail "No successful release build of ${tag} at ${commit}."
gh run download "${run}" --repo "${repo}" --name release-bundle --dir "${work}/dist"
[[ -s "${work}/dist/projektrack-${version}.zip" && -s "${work}/dist/projektrack-${version}.image.txt" ]] || fail 'The build is incomplete.'
(cd "${work}/dist" && sha256sum --strict -c SHA256SUMS >/dev/null) || fail 'The files do not match the checksums of the build.'
cp "${work}/dist/SHA256SUMS" "${work}/built"

deploy/release/sign.sh "${work}/dist" "${key}" >/dev/null
cmp -s "${work}/built" "${work}/dist/SHA256SUMS" || fail 'The checksums of the build do not name exactly its files.'

# An update is checked with the keys of the running release, so this release
# and the newest earlier one with keys must both trust the signing key.
git show "${commit}:resources/release/public-keys.txt" > "${work}/keys"
deploy/release/verify.sh "${work}/dist" "${work}/keys" >/dev/null || fail "${tag} does not ship the public key of this private key."
previous=''
for candidate in $(git tag --list 'v[0-9]*' --sort=-v:refname --merged "${commit}^"); do
    git show "${candidate}:resources/release/public-keys.txt" > "${work}/keys" 2>/dev/null || continue
    grep -qve '^#' -e '^[[:space:]]*$' "${work}/keys" || continue
    deploy/release/verify.sh "${work}/dist" "${work}/keys" >/dev/null || fail "${candidate} does not trust this key; its instances could not update."
    previous="${candidate}"
    break
done

echo "Release:   ${tag}"
echo "Commit:    ${commit}  $(git log -1 --format=%s "${commit}")"
echo "Since:     ${previous:-(first release with keys)}"
echo "Build:     https://github.com/${repo}/actions/runs/${run}"
echo "Image:     $(cat "${work}/dist/projektrack-${version}.image.txt")"
[[ -z "${previous}" ]] || git log --oneline "${previous}..${commit}"
read -r -p "Publish ${tag} signed with this key? Type ${tag} to approve: " answer
[[ "${answer}" == "${tag}" ]] || fail 'Not approved. Nothing was published.'

gh release create "${tag}" --repo "${repo}" --verify-tag --title "Projektrack ${version}" \
    --notes 'Verify before installing or updating: see docs/admin/releases.md.' "${work}/dist/"*
