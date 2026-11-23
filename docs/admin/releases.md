# Releases and their verification

How releases are built and signed (S-0924, security baseline section 9), how operators verify them, and how maintainers manage the release key.

## What a release contains

A tag `vX.Y.Z` on a commit of `main` whose CI is green starts `.github/workflows/release.yml`. The GitHub release then holds:

| File | Content |
|---|---|
| `projektrack-X.Y.Z.zip` | The application with `vendor/` (no development packages) and compiled assets, built from the committed files of the tag only. Runs on plain AMP hosting without Composer or Node. |
| `projektrack-X.Y.Z.cdx.json` | CycloneDX SBOM of the Composer and npm packages. |
| `projektrack-X.Y.Z.image.cdx.json` | CycloneDX SBOM of the container image. |
| `projektrack-X.Y.Z.image.txt` | The image reference with its digest, for example `ghcr.io/projektra/projektrack@sha256:...`. |
| `SHA256SUMS` | SHA-256 of every file above. |
| `SHA256SUMS.sig` | Ed25519 signature of `SHA256SUMS` with the release key. |
| `SHA256SUMS.cosign.bundle` | A second, keyless signature of `SHA256SUMS` by the release workflow. |

The image `ghcr.io/projektra/projektrack:X.Y.Z` is scanned before it is pushed (the release stops on a fixable critical vulnerability), carries a build provenance attestation and an SBOM attestation, and is signed with cosign.

## Verify a ZIP before installing it

Put `SHA256SUMS` and `SHA256SUMS.sig` next to the ZIP. With a running Projektrack (for an update), or from the unpacked ZIP itself (for a new installation):

```
php artisan projektrack:release:verify projektrack-X.Y.Z.zip
```

It checks the signature with the release keys that ship in `resources/release/public-keys.txt` and the checksum of the ZIP, and refuses on any difference: a changed ZIP, a changed checksum list, an unknown key or no key at all. The updater (S-0927, S-0928) runs the same check with the keys of the release that is running, so a key is trusted only after a signed release brought it.

## Verify an image before running it

```
cosign verify ghcr.io/projektra/projektrack@sha256:<digest> \
  --certificate-identity-regexp '^https://github.com/PROJEKTRA/projektrack/\.github/workflows/release\.yml@refs/tags/v' \
  --certificate-oidc-issuer https://token.actions.githubusercontent.com
```

Run images by digest, not by tag. `docs/admin/docker.md` describes the stack.

## Release key (maintainers)

The Ed25519 release key is separate from the license key and never exists on an instance or in the repository.

1. On an offline or otherwise trusted machine:

   ```
   openssl genpkey -algorithm ed25519 -out release-signing.pem
   openssl pkey -in release-signing.pem -pubout -outform DER | tail -c 32 | base64
   ```

2. Add the printed public key as a line to `resources/release/public-keys.txt` and commit it.
3. In the repository settings, create the environment `release` with required reviewers, and store the private key as its secret: `gh secret set RELEASE_SIGNING_KEY --env release < release-signing.pem`
4. Keep the PEM file in the password vault only, then delete it from the machine.

Rotation: add the new public key next to the old one, ship a release signed with the old key, switch the secret to the new key, and remove the old public key only after a release signed with the new key has shipped. After a suspected compromise, remove the old key at once and publish a security advisory.

## Cutting a release

1. Make sure CI is green on the `main` commit.
2. `git tag -s vX.Y.Z <commit>` and `git push origin vX.Y.Z`.
3. Approve the `release` environment when the workflow asks.

The workflow refuses a tag that is not a version, a commit whose CI on `main` is not green, a missing release key, and a ZIP that its own verification rejects.
