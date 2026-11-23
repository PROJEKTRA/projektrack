#!/usr/bin/env bash
# Copyright (c) PROJEKTRA IT SAVJETOVANJE d.o.o. Travnik (PROJEKTRA IT Consulting), projektra.net
# SPDX-License-Identifier: AGPL-3.0-only
#
# CI test of deploy/release/approve.sh (S-0924): a local origin, a stand-in for
# gh that serves a prepared build and notes what is published. Every refusal
# must publish nothing. Runs from the repository root.
set -Eeuo pipefail

root="$(git rev-parse --show-toplevel)"
failures=0
work="$(mktemp -d)"
trap 'rm -rf "${work}"' EXIT
check() { local name="$1"; shift; if "$@"; then printf 'ok    %s\n' "${name}"; else printf 'FAIL  %s\n' "${name}"; failures=$((failures + 1)); cat "${work}/out" 2>/dev/null || true; fi; }

# The trusted copy of the scripts, apart from the repository under test.
mkdir "${work}/tool"
cp "${root}/deploy/release/approve.sh" "${root}/deploy/release/verify.sh" "${work}/tool/"

new_key() { openssl genpkey -algorithm ed25519 -out "$1" 2>/dev/null; }
public_of() { openssl pkey -in "$1" -pubout -outform DER | tail -c 32 | base64; }
new_key "${work}/key.pem"
new_key "${work}/new.pem"
new_key "${work}/foreign.pem"

export GIT_AUTHOR_NAME=CI GIT_AUTHOR_EMAIL=ci@example.test GIT_COMMITTER_NAME=CI GIT_COMMITTER_EMAIL=ci@example.test
git init -q --bare -b main "${work}/origin.git"
git clone -q "${work}/origin.git" "${work}/repo" 2>/dev/null
cd "${work}/repo"
git checkout -q -b main
commit() { printf '%s\n' "$2" > "$1"; git add -A; git commit -q -m "$3"; git rev-parse HEAD; }
mkdir -p resources/release
first="$(commit resources/release/public-keys.txt "$(public_of "${work}/key.pem")" 'first release')"
reviewed="$(commit change.txt 'reviewed' $'reviewed \e[2Kchange')"
printf '%s\n%s\n' "$(public_of "${work}/key.pem")" "$(public_of "${work}/new.pem")" > resources/release/public-keys.txt
rotated="$(git commit -qam 'new key' && git rev-parse HEAD)"
git push -q origin main
git checkout -q -b aside
aside="$(commit evil.txt evil 'not on main')"
git checkout -q main
git tag v0.9.0 "${reviewed}"
git tag v0.9.1 "${rotated}"
git tag v0.9.2 "${aside}"
git push -q origin v0.9.0 v0.9.1 v0.9.2

# Stand-in for gh; controlled by files in ${work}.
mkdir "${work}/bin"
cat > "${work}/bin/gh" <<EOF
#!/usr/bin/env bash
case "\$1 \$2" in
    'run list') case "\$*" in *ci.yml*) cat '${work}/ci' ;; *) cat '${work}/run' ;; esac ;;
    'run download') cp -r '${work}/build' "\${@: -1}" ;;
    'release create') printf '%s\n' "\$@" > '${work}/published'; cp -r "\$(dirname "\${@: -1}")" '${work}/assets' ;;
    *) exit 9 ;;
esac
EOF
chmod +x "${work}/bin/gh"
export PATH="${work}/bin:${PATH}"

build() {
    rm -rf "${work}/build" "${work}/published" "${work}/assets"
    mkdir "${work}/build"
    echo zip > "${work}/build/projektrack-$1.zip"
    echo sbom > "${work}/build/projektrack-$1.cdx.json"
    echo sbom > "${work}/build/projektrack-$1.image.cdx.json"
    echo "ghcr.io/projektra/projektrack@sha256:$(printf 'a%.0s' $(seq 64))" > "${work}/build/projektrack-$1.image.txt"
    (cd "${work}/build" && sha256sum projektrack-* > SHA256SUMS)
    echo 1 > "${work}/ci"
    echo 42 > "${work}/run"
}
approve() { printf '%s\n' "${answer}" | "${work}/tool/approve.sh" "$@" > "${work}/out" 2>&1; }
refused() { ! approve "$@" && [[ ! -e "${work}/published" ]]; }
said() { grep -q "$1" "${work}/out"; }

answer=v0.9.0
build 0.9.0
check 'the reviewed release is published' approve v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
check 'its assets verify' "${root}/deploy/release/verify.sh" "${work}/assets" resources/release/public-keys.txt
check 'no escape sequence reaches the terminal' bash -c '! grep -q "$(printf "\033")" "$1"' _ "${work}/out"
check 'it names what changed' said 'change.txt'
build 0.9.0
check 'the first release needs no previous one' approve v0.9.0 "${reviewed}" first "${work}/key.pem"

build 0.9.0
check 'another commit than reviewed' refused v0.9.0 "${first}" first "${work}/key.pem"
check 'a short commit' refused v0.9.0 "${reviewed:0:12}" first "${work}/key.pem"
build 0.9.2
answer=v0.9.2
check 'a commit that is not on main' refused v0.9.2 "${aside}" "${first}" "${work}/key.pem"
check 'it says why' said 'not on main'
answer=v0.9.0
build 0.9.0
check 'a previous release that is no ancestor' refused v0.9.0 "${reviewed}" "${rotated}" "${work}/key.pem"
check 'an untrusted key' refused v0.9.0 "${reviewed}" "${first}" "${work}/foreign.pem"
answer=v0.9.1
build 0.9.1
check 'a key the previous release does not trust' refused v0.9.1 "${rotated}" "${reviewed}" "${work}/new.pem"
check 'it says why' said 'could not update'
answer=no
build 0.9.0
check 'a wrong answer' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
check 'it says so' said 'Nothing was signed'
answer=v0.9.0
build 0.9.0; echo 0 > "${work}/ci"
check 'red CI' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
build 0.9.0; : > "${work}/run"
check 'no build' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
build 0.9.0; echo x >> "${work}/build/projektrack-0.9.0.zip"
check 'a changed file' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
build 0.9.0; echo x > "${work}/build/extra.txt"
check 'an extra file' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
build 0.9.0; rm "${work}/build/projektrack-0.9.0.cdx.json"; (cd "${work}/build" && sha256sum projektrack-* > SHA256SUMS)
check 'a missing SBOM' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"
build 0.9.0; echo 'ghcr.io/other/image:latest' > "${work}/build/projektrack-0.9.0.image.txt"; (cd "${work}/build" && sha256sum projektrack-* > SHA256SUMS)
check 'another image' refused v0.9.0 "${reviewed}" "${first}" "${work}/key.pem"

printf '\nResult: %s failed\n' "${failures}"
[[ ${failures} -eq 0 ]]
