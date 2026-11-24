# Licenses

A license is a signed text (`PTKL1...`) that an instance checks offline with the public keys of its release (S-0974, ADR-0003). Without a license an instance is Community; nothing is ever locked or deleted.

## Install a license

In the browser: **Admin > License** shows the instance id, edition, users in use and licensed, expiry and what the plan includes, and installs a license from a file or pasted text. It is open to administrators of the operator tenant with the permission `core.license.manage`. On the console:

1. `php artisan projektrack:license:show` prints the instance id (`inst_...`). Send it and the host name to PROJEKTRA. The id stays the same after an update and after a restore.
2. Put the license you receive into a file and run `php artisan projektrack:license:install <file>`. With Docker: `docker compose exec -T app php artisan ...`, with the file in the storage volume.

A license is refused if it was changed, is signed with an unknown or revoked key, belongs to another installation, has not started or has ended, or is an earlier issue of one that was installed before. The installed license then stays.

| State | Meaning |
|---|---|
| `active`, `pending_payment`, `grace` | Commercial features work. Grace follows the expiry, for the days the license names |
| `read_only`, `not_yet_valid` | Commercial features can be viewed and exported, not changed |
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

## Sell a license (licensor)

On the licensor's own instance only: `VENDOR_INSTANCE=true` in `.env`, then `php artisan projektrack:modules:enable <slug> licensing`. Under "License sale":

1. Create the plan once (prices per user and term, onboarding).
2. Confirm an order: it drafts the invoice (check and issue it as usual) and opens 14 days of grace.
3. Download the file for signing, sign it on the trusted machine (`issue.php issue`), enter the license text and send it to the customer.
4. When the invoice is paid, the order asks for a new issue with the next serial: download, sign, enter, send.

The private key never comes to this instance. Orders that are not paid in time are told to the managers once. When the invoice differs from the order, is credited or a payment is returned, the order is "to review" and nothing can be signed. After a partial credit, credit and refund the rest first. An invoice that is cancelled, or credited and refunded in full, is drafted again from the order at the agreed prices; an order is cancelled after its invoice. The start is today or up to 31 days back.

The vendor build: `VENDOR=1 deploy/release/build-zip.sh <version> <folder>`. Every other release contains nothing of `ee/`.
