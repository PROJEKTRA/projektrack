# Releases and their verification

How releases are built and signed (S-0924, security baseline section 9), how operators verify them, and how maintainers manage the release key.

## What a release contains

A tag `vX.Y.Z` on a commit of `main` whose CI is green starts `.github/workflows/release.yml`, which builds but signs nothing. A maintainer approves the build by signing it on their own machine. The GitHub release then holds:

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

Do not run anything from the ZIP before it is verified: a forged ZIP would bring its own keys and its own verifier. Take the release key from a source that is neither the ZIP nor the repository: the delivery contract or projektra.net. Anyone with write access to the repository could replace both. Then, with `SHA256SUMS` and `SHA256SUMS.sig` next to the ZIP:

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

The Ed25519 release key is separate from the license key. It is never on GitHub, not even as a secret, and never on an instance. It is used on the maintainer's machine.

1. Make two keys with a passphrase on a trusted machine, one for use and one standby:

   ```
   openssl genpkey -algorithm ed25519 -aes-256-cbc -out release-signing.pem
   openssl genpkey -algorithm ed25519 -aes-256-cbc -out release-standby.pem
   openssl pkey -in release-signing.pem -pubout -outform DER | tail -c 32 | base64
   openssl pkey -in release-standby.pem -pubout -outform DER | tail -c 32 | base64
   ```

2. Add both public keys to `resources/release/public-keys.txt` before the first release. A standby key cannot be added after a loss: instances only accept what their running release trusts.
3. Keep the two private keys in separate places, not on the same machine. Publish both public keys on projektra.net and in the delivery contract.
4. Read `deploy/release/approve.sh` and `verify.sh`, and keep a copy of both next to the key. Use only that copy. Replace it only after reading the difference.

Rotation: add the new public key, ship a release signed with the old one, then sign with the new key, and remove the old public key only after that release has shipped. `approve.sh` refuses a key that the previous release does not trust.

Stolen or lost key: sign the next release with the standby key, remove the old public key and add a new standby in it, and publish a security advisory. Instances trust a stolen key until they run that release, so every operator must update at once.

## Cutting a release

1. Review every change since the previous release and note the full id of the reviewed commit and of the previous release. CI on `main` must be green for it.
2. `git tag vX.Y.Z <reviewed commit>` and `git push origin vX.Y.Z`.
3. When the build is green, run your own copy inside a clone: `/path/to/approve.sh vX.Y.Z <reviewed commit> <commit of the previous release> /path/to/release-signing.pem` (for the first release `first` instead of the previous commit). It shows every change, and in full those to keys, release scripts and workflows. It signs and publishes only after you type the tag.

The workflow refuses a tag that is not a version, a commit that is not on `main` or whose CI is not green, and a missing SBOM. `approve.sh` checks the tag against the reviewed commit, repeats the other checks, and refuses files that the build did not name.

GitHub cannot enforce branch or tag rules on this repository's plan. Anyone with write access can push to `main`, set tags and start builds. What protects existing instances is the key together with the review: nobody can give them an update they accept without a maintainer signing it.

## Tests before production

No release reaches a production system unless all five kinds passed on its commit. CI runs them on every pull request and on `main`; the release workflow refuses a commit whose CI on `main` is not green.

| Kind | CI job |
|---|---|
| Unit | Static checks (Vitest), Tests (Pest) |
| Integration | Tests on MySQL, MariaDB, PostgreSQL and SQLite with at least 85 % coverage, e-invoices, plain hosting, Docker, update and rollback |
| End to end | Browser tests |
| Performance | Performance: 100,000 time entries, p95 of a page at most 300 ms, ten users at once at most 1 second and no error |
| Security | Security (static analysis, secrets), Security (attack scan), dependency audit, crawl; independent review per milestone |
