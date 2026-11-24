# QA report X.Y

Copy to `docs/qa/X.Y.md` for every major release. The release workflow refuses version X.Y.0 until the line below reads `Decision: approved`. Pre-releases (X.Y.0-rc.1) are built without it.

Decision: open

Decided by, on:

Commit tested:

## 1. Cycle

1. Test: all five kinds on one commit, plus independent QA, security and, for money, tax or time, finance review.
2. Record every finding in section 3.
3. Repair each finding with a test that fails before and passes after.
4. Retest the whole on the new commit. Repeat until section 4 holds.

## 2. Results

| Kind | What ran | Result | Evidence |
|---|---|---|---|
| Unit | | | |
| Integration | | | |
| End to end | | | |
| Performance | | | |
| Security | | | |
| Independent reviews | | | |
| Manual (usability, devices, documents) | | | |

## 3. Findings

| Id | Severity | Finding | Found by | State | Repair and test |
|---|---|---|---|---|---|

State: open, repaired, retested, accepted (with reason and backlog entry).

## 4. Exit criteria

- All five kinds green on the commit to be released.
- No open critical, high or medium finding; a medium may be accepted only by the product owner, in writing here.
- Every Must story has an automated or recorded manual test.
- Update from the previous release, rollback, backup and restore tested.

## 5. Not tested

