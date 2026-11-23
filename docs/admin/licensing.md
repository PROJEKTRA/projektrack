# Licenses

A license is a signed text (`PTKL1...`) that an instance checks offline with the public keys of its release (S-0974, ADR-0003). Without a license an instance is Community; nothing is ever locked or deleted.

## Install a license

1. `php artisan projektrack:license:show` prints the instance id (`inst_...`). Send it and the host name to PROJEKTRA. The id stays the same after an update and after a restore.
2. Put the license you receive into a file and run `php artisan projektrack:license:install <file>`. With Docker: `docker compose exec -T app php artisan ...`, with the file in the storage volume.

A license is refused if it was changed, is signed with an unknown or revoked key, belongs to another installation, has not started or has ended, or is an earlier issue of one that was installed before. The installed license then stays.

| State | Meaning |
|---|---|
| `active`, `pending_payment`, `grace` | Commercial features work. Grace follows the expiry, for the days the license names |
| `read_only`, `not_yet_valid` | Commercial features can be viewed and exported. Their enforcement comes with S-0977 |
| `invalid` | The installed license was changed, or its key is not trusted any more: like `read_only`, with the reason in `license:show` and the log |

The state is computed from the system clock. If the clock is more than 48 hours behind a time seen earlier, `license:show` warns and the log says so; nothing is locked. After a clock that ran wild into the future, clear it with `UPDATE instance_state SET latest_time = NULL`. Installations with several tenants set `DOCTOR_OPERATOR_TENANT`, so that a new license is recorded in that tenant's audit log.

## Issue a license (licensor)

On a trusted machine with a checkout, never on an instance:

```
export LICENSE_KEY_PASSPHRASE=...   # at least 16 characters
php ee/licensing/issue.php keygen ptk-2026-01 /safe/place/license-key.json
php ee/licensing/issue.php issue /safe/place/license-key.json payload.json > license.txt
```

`keygen` prints the entry for `config/license-keys.php`; it must be in a release before the first license of that key is installed. The key file is encrypted with the passphrase; keep both apart, and never in the repository or on GitHub. The license key is not the release key.

The payload is the example of ADR-0003 section 3. `kid` and `issued_at` are set by the tool, which also checks the result as an instance of this checkout would, and warns if the key is not in it yet. Give every new issue of the same `license_id` a higher `serial`.

Rotation: ship the new key one release before it is used, set the old one to `retired` when the new one is in use. After a compromise set it to `revoked`, ship a release, and issue every affected license anew: on instances a license whose key is revoked becomes `invalid`. Use `revoked`, never `retired`, for a stolen key: who holds it can date a license back into its validity.
