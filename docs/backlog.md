# Backlog

This file holds ideas and requests that are outside the current milestone. Nothing here is committed scope. An item moves into a release only when the product owner accepts it and writes a story for it.

Rules:

- One row per idea. IDs are stable (`BL-001`, `BL-002`, ...).
- "Source" names where the idea came from (customer, internal use, review, support).
- Dates use DD.MM.YYYY.
- "Target release candidate" is a proposal, not a commitment.

| ID | Idea | Source | Date | Target release candidate |
|---|---|---|---|---|
| BL-001 | Audit checkpoints: daily head hash per chain signed with the instance Ed25519 key, exportable off-system, checked by `projektrack:audit:verify` (security baseline 8.2). Needs the instance key from licensing. | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-002 | Instance audit chain for operator events (no tenant). | Internal review (S-0907) | 18.11.2026 | 0.9.2 |
| BL-003 | Database guard against UPDATE and DELETE on audit tables: restricted database user on P2 to P5, triggers on P1 (security baseline 8.2). | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-004 | Admin > Security: run the audit verification on demand and browse or export the audit log. | Internal review (S-0907) | 18.11.2026 | 0.9.1 |
| BL-005 | Admin > Users: lift a sign-in lock from the screen (today `projektrack:auth:unlock`), audited. | Internal review (S-0903) | 19.11.2026 | 0.9.1 |
| BL-006 | Settings > Security: list each session with device and last activity and sign out one at a time. | Internal review (S-0903) | 19.11.2026 | 0.9.2 |
| BL-007 | Larger bundled list of common passwords (open-licensed source, 12 characters and more). | Internal review (S-0903) | 19.11.2026 | 0.9.1 |
| BL-008 | Soft lockout: a device with an earlier successful sign-in (signed device cookie) is not locked by failures from elsewhere, so attackers cannot lock owners out (ASVS 2.2.1). | Security review (S-0903) | 19.11.2026 | 0.9.1 |
