# Releases and their verification

How releases are built and signed (S-0924, security baseline section 9), how operators verify them, and how maintainers manage the release key.

## What a release contains

A tag `vX.Y.Z` on a commit of `main` whose CI is green starts `.github/workflows/release.yml`, which builds but signs nothing. A maintainer approves the build by signing it offline. The GitHub release then holds:

| File | Content |
|---|---|
| `projektrack-X.Y.Z.zip` | The application with `vendor/` (no development packages) and compiled assets, built from the committed files of the tag only. Runs on plain AMP hosting without Composer or Node. |
| `projektrack-X.Y.Z.cdx.json` | CycloneDX SBOM of the Composer and npm packages. |
| `projektrack-X.Y.Z.image.cdx.json` | CycloneDX SBOM of the container image. |
| `projektrack-X.Y.Z.image.txt` | The image reference with its digest, for example `ghcr.io/projektra/projektrack@sha256:...`. |
| `SHA256SUMS` | SHA-256 of every file above. |
| `SHA256SUMS.sig` | Ed25519 signature of `SHA256SUMS` with the release key. |

The image is pushed by digest and scanned in exactly that form (the build stops on a fixable critical vulnerability). It has no tag: its digest is in `projektrack-X.Y.Z.image.txt`, which the signed `SHA256SUMS` covers, so the one signature covers the image too.

## Verify a ZIP for a new installation

Do not run anything from the ZIP before it is verified: a forged ZIP would bring its own keys and its own verifier. Take the release key from a source that is not the ZIP: the line in `resources/release/public-keys.txt` of the repository at github.com/PROJEKTRA/projektrack, which projektra.net also publishes. Then, with `SHA256SUMS` and `SHA256SUMS.sig` next to the ZIP:

```
KEY='<the base64 key>'
(printf '\060\052\060\005\006\003\053\145\160\003\041\000'; printf '%s' "$KEY" | base64 -d) > release.der
openssl pkey -pubin -inform DER -in release.der -out release.pub
openssl pkeyutl -verify -pubin -inkey release.pub -rawin -in SHA256SUMS -sigfile SHA256SUMS.sig
sha256sum -c --ignore-missing SHA256SUMS
```

Both must succeed (`Signature Verified Successfully` and `OK` for the ZIP).

## Verify a ZIP for an update

On a running instance, with `SHA256SUMS` and `SHA256SUMS.sig` next to the ZIP:

```
php artisan projektrack:release:verify projektrack-X.Y.Z.zip --release=X.Y.Z
```

It checks the signature with the release keys shipped with the running release, the checksum of the ZIP, and that the ZIP is exactly release X.Y.Z (name and `VERSION` inside), so an older genuine release cannot be passed off as an update. It refuses on any difference, and when no key is shipped. The trusted keys come only from the shipped file; there is no setting or environment variable for them. The updater (S-0927, S-0928) runs the same check and must also refuse a version that is not newer than the running one.

## Verify an image before running it

Verify `projektrack-X.Y.Z.image.txt` as above (`sha256sum -c` then names it), and run the image by the digest in that file. An update does both: `./projektrack update <folder>` (`docs/admin/docker.md`).

## Release key (maintainers)

The Ed25519 release key is separate from the license key and never exists on an instance or in the repository.

1. On an offline or otherwise trusted machine:

   ```
   openssl genpkey -algorithm ed25519 -out release-signing.pem
   openssl pkey -in release-signing.pem -pubout -outform DER | tail -c 32 | base64
   ```

2. Add the printed public key as a line to `resources/release/public-keys.txt` and commit it.
3. Keep the PEM file encrypted, offline (for example on a USB drive) with a copy in the password vault. It never goes to GitHub, not even as a secret.

Rotation: add the new public key next to the old one, ship a release signed with the old key, switch the secret to the new key, and remove the old public key only after a release signed with the new key has shipped. `approve.sh` enforces the order: it checks each release also with the keys of the previous release. After a suspected compromise, remove the old key at once and publish a security advisory.

## Cutting a release

1. Make sure CI is green on the `main` commit.
2. `git tag -s vX.Y.Z <commit>` and `git push origin vX.Y.Z`.
3. When the build is green, check what is in it, and sign it from a clean clone of `main`: `deploy/release/approve.sh vX.Y.Z /path/to/release-signing.pem`. It shows tag, commit, changes, build and image, and publishes only after you type the tag.

The workflow refuses a tag that is not a version, a commit that is not on `main` or whose CI on `main` is not green, and a missing SBOM. `approve.sh` checks tag, commit and CI again on the maintainer's machine, takes the files from the artifact of the build and not from anything that can be replaced later, and refuses a key that this or the previous release does not trust.

GitHub cannot enforce branch or tag rules on this repository's plan, so the offline key is what protects a release. Anyone with write access can push a tag and start a build; nobody can publish a release that instances accept without the key.
