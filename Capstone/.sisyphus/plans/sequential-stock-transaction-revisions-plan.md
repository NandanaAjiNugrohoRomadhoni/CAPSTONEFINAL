# Multiple Sequential Revisions for Stock Transactions

## 1. Goal
Support multiple revisions over time for a single stock transaction while preserving the existing runtime architecture:
- revisions remain child rows with `is_revision = true`
- every revision keeps `parent_transaction_id` pointing to the original non-revision transaction
- revisions do **not** form a chain of `revision -> revision` records

This change is therefore **sequential sibling revisions within one lineage**, not chained revisions.

## 2. Grounded Current-State Findings
- Current runtime already supports revisions through `POST /api/v1/stock-transactions/{id}/submit-revision`, `POST /api/v1/stock-transactions/{id}/approve`, and `POST /api/v1/stock-transactions/{id}/reject`.
- `App\Services\StockTransactionService::submitRevision()` rejects revising a row whose `is_revision` is already `true`.
- `App\Services\StockTransactionService::approveRevision()` currently computes delta against the original parent transaction details, not the latest approved sibling.
- `App\Services\StockTransactionService::approveRevision()` currently blocks approval if **any** approved sibling exists for the same `parent_transaction_id`.
- The docs currently claim a transaction can only have one active revision at a time, but the code does **not** enforce a one-pending-revision rule at submit time.
- `App\Models\StockTransactionModel::findApprovedRevisionByParentId()` currently has no ordering, so it is not safe for deterministic “latest approved” selection.
- The schema provides `parent_transaction_id` as a self-FK, but no revision-order column and no revision-specific uniqueness/index hardening.

## 3. Explicit Domain Rules

### 3.1 Lineage model
- A stock transaction lineage has exactly one original root transaction (`is_revision = false`).
- All revisions in that lineage point to that root via `parent_transaction_id`.
- `submitRevision()` continues rejecting attempts to revise a revision row directly.

### 3.2 One pending revision at a time
- A lineage may have at most one active `PENDING` revision at any moment.
- If a `PENDING` sibling already exists for the same `parent_transaction_id`, a new submission is rejected.
- Once that pending revision becomes `APPROVED` or `REJECTED`, a new revision may be submitted.

### 3.3 Effective baseline rule
When approving a pending revision, the baseline must be:
1. the most recently approved revision in the same lineage, if one exists; otherwise
2. the original parent transaction.

This replaces the current behavior that always compares against the original parent.

### 3.4 Delta rule
- Approval applies only the **net stock correction** between the pending revision snapshot and the effective baseline snapshot.
- It must never replay the entire revised transaction effect on top of already-applied stock.
- Rejection never changes `items.qty`.

### 3.5 Transaction scope rule
- The transaction type and mutation direction stay inherited from the original parent transaction type.
- A revision remains a full replacement snapshot of stock-affecting detail rows for that transaction.
- Detail comparison is by `item_id` using normalized base-unit quantities.

## 4. Backend Changes

### 4.1 `App\Models\StockTransactionModel`
Add or change helpers for lineage queries:

- `findLatestApprovedRevisionByParentId(int $parentId, int $approvedStatusId, ?int $excludeId = null): ?array`
  - filter by `parent_transaction_id`, `is_revision = true`, `approval_status_id = APPROVED`, `deleted_at IS NULL`
  - order by `id DESC`
  - return one row
- `hasPendingRevision(int $parentId, int $pendingStatusId, ?int $excludeId = null): bool`
  - same lineage filter with `approval_status_id = PENDING`
- keep `findApprovedRevisionByParentId()` only if still needed elsewhere; otherwise replace usages with the latest-approved helper

### 4.2 `App\Services\StockTransactionService::submitRevision`
- Keep the existing guard that rejects revising a revision.
- Before insert, check `hasPendingRevision($parentTransactionId, $pendingStatusId)`.
- If true, reject submission with a clear message such as: `Another revision for this transaction is still pending review.`
- Keep current forbidden-field validation and current behavior that submitting a revision does not mutate stock.
- Preserve current payload shape and response body unless controller/API contract is intentionally updated.

### 4.3 `App\Services\StockTransactionService::approveRevision`
- Remove the current rule that blocks approval merely because any approved sibling exists.
- Instead:
  1. load the pending revision
  2. resolve its lineage root from `parent_transaction_id`
  3. load the latest approved sibling in that lineage excluding the current revision
  4. choose baseline = latest approved sibling or root parent if none exists
  5. load baseline details and revision details
  6. compute per-item signed delta using `(revisionQty - baselineQty)` and existing direction rules
  7. apply signed item deltas atomically
  8. update revision status to `APPROVED`
- The result is that a second approved revision is allowed later, but only as the next sequential revision in the same lineage.

### 4.4 `App\Services\StockTransactionService::rejectRevision`
- Keep current behavior: only `PENDING` can be rejected, status becomes `REJECTED`, no stock mutation.
- Rejection reopens the submission slot because the lineage then has no pending revision.

## 5. Concurrency and Transaction Safety
Because CI4 does not provide a Query Builder `FOR UPDATE` helper, use raw SQL locking where needed inside the service transaction boundary.

### 5.1 Submit path
Inside one DB transaction:
- lock the root transaction row for the lineage
- check again for an existing pending sibling while holding the lock
- insert the new pending revision only if no pending sibling exists

### 5.2 Approve path
Inside one DB transaction:
- lock the revision row being approved
- verify it is still `PENDING`
- lock/read the lineage root and latest approved baseline consistently
- compute and apply deltas once
- update approval status once

### 5.3 Reject path
Inside one DB transaction:
- lock the revision row being rejected
- verify it is still `PENDING`
- update to `REJECTED`

### 5.4 Race-condition goals
This plan should prevent:
- two pending siblings being submitted concurrently
- stale approval of a revision that has already been approved/rejected by another admin
- baseline reads that use an outdated approved sibling
- double-application of stock deltas during approval retries

## 6. Schema and Query Hardening

### 6.1 Minimal correctness path
No new lineage columns are strictly required for the first implementation if all of these become true:
- only one pending revision exists at a time
- approved revisions are resolved deterministically by `id DESC`
- all revisions in the lineage always point to the original parent

### 6.2 Recommended hardening
Add a migration-level index to support lineage queries efficiently, for example a composite index covering parent/status/revision lookups used by:
- `hasPendingRevision()`
- `findLatestApprovedRevisionByParentId()`

### 6.3 Deferred hardening option
If later auditability needs become stronger, consider adding explicit lineage metadata such as `revision_no` or `baseline_transaction_id`, but that is **not required** for the first implementation.

## 7. Documentation Updates
Update these docs so they match the new runtime behavior:
- `backend/docs/guides/by-workflow/stock-correction-workflow.md`
- `backend/docs/guides/by-user/gudang-quickstart.md`
- `backend/docs/reference/schema.md`
- `backend/docs/reference/api-contract.md`
- optionally `backend/docs/architecture/runtime-status.md` if the key constraint summary should mention sequential sibling revisions explicitly

Doc updates must say:
- one pending revision per original transaction lineage
- successive approved revisions are allowed over time
- approval baseline is latest approved revision else original parent
- revision-on-revision is still not allowed

## 8. SDK / Frontend Impact
No endpoint shape change is required.

Frontend/SDK updates are limited to:
- handling the new pending-conflict error case on submit
- understanding that approval of later revisions is now valid behavior
- not assuming that a previously approved sibling permanently blocks future revisions

## 9. Test Plan
Add or update feature tests for:
1. cannot submit a second revision while one sibling is pending
2. can submit a new revision after previous sibling is rejected
3. can submit a new revision after previous sibling is approved
4. second approval uses the latest approved sibling as baseline, not the original parent
5. zero-delta approval against the latest approved sibling leaves stock unchanged
6. rejecting a pending revision still leaves stock unchanged
7. concurrent/state-conflict paths keep status and stock unchanged on failure
8. revision-on-revision remains rejected

Existing tests that assert `Another revision for this transaction has already been approved.` as a permanent block must be rewritten to reflect the new sequential behavior.

## 10. Executable Verification Wave

### 10.1 Primary test tool
- Use PHPUnit feature tests from the backend root.
- Primary command after code changes:
  - `composer test -- tests/feature/Api/V1/StockTransactionsTest.php`
- If the repo test runner rejects that argument form, run:
  - `vendor/bin/phpunit tests/feature/Api/V1/StockTransactionsTest.php`

### 10.2 Required automated scenarios
Add named feature tests that prove these exact behaviors:

1. **pending sibling submit conflict**
   - Setup: create parent transaction, submit first revision, submit second revision before review.
   - Expected result: second submit returns failure; first revision remains `PENDING`; stock unchanged from the first submit attempt.

2. **re-submit allowed after rejection**
   - Setup: create parent transaction, submit revision A, reject revision A, submit revision B.
   - Expected result: revision B is created successfully as `PENDING`; stock still unchanged after rejection and submission.

3. **re-submit allowed after approval**
   - Setup: create parent transaction, submit revision A, approve revision A, submit revision B.
   - Expected result: revision B is created successfully as `PENDING`; the lineage can continue after an approved revision.

4. **approval baseline uses latest approved sibling**
   - Setup: create parent transaction, approve revision A, then approve revision B with different detail quantities.
   - Expected result: stock mutation from approving revision B equals `revisionB - revisionA`, not `revisionB - parent`.

5. **zero delta against latest approved baseline**
   - Setup: create parent transaction, approve revision A, submit revision B with the same normalized detail quantities as revision A.
   - Expected result: approving revision B succeeds and leaves `items.qty` unchanged.

6. **reject leaves stock unchanged**
   - Setup: create parent transaction, submit revision, reject it.
   - Expected result: `items.qty` remains unchanged and revision status becomes `REJECTED`.

7. **failed approval keeps status and stock unchanged**
   - Setup: create parent transaction, submit revision, force an approval failure path such as insufficient stock or stale state.
   - Expected result: approval returns failure, revision remains `PENDING`, no partial stock mutation is committed.

8. **revision-on-revision remains blocked**
   - Setup: create parent transaction, submit revision A, attempt to submit a revision using revision A as the route target.
   - Expected result: failure with the existing revision-on-revision validation behavior.

### 10.3 Query/model verification
- Add model-level assertions or focused service/feature assertions that prove:
  - the latest-approved helper returns the highest approved revision ID in the lineage
  - the pending helper returns true only when a pending sibling exists
  - approved and rejected siblings do not falsely trigger pending checks

### 10.4 Manual route/contract verification
After automated tests pass, run targeted API verification against the changed flow:
- `POST /api/v1/stock-transactions/{id}/submit-revision`
- `POST /api/v1/stock-transactions/{id}/approve`
- `POST /api/v1/stock-transactions/{id}/reject`

Expected contract outcomes:
- submit success remains `201`
- approve success remains `200`
- reject success remains `200`
- parent-not-found and revision-not-found behavior remains unchanged
- the new pending-conflict submit case returns a client-visible validation failure with a clear message

### 10.5 Schema/index verification
After adding the migration, verify index presence using the project database tooling or SQL inspection.
Expected outcome:
- the new lineage-supporting index exists on `stock_transactions`
- the new helper queries use that index path logically for `parent_transaction_id` + approval status lookups

### 10.6 Documentation verification
Read these updated docs after implementation:
- `backend/docs/guides/by-workflow/stock-correction-workflow.md`
- `backend/docs/guides/by-user/gudang-quickstart.md`
- `backend/docs/reference/schema.md`
- `backend/docs/reference/api-contract.md`

Expected outcome in docs:
- no statement still claims that any approved sibling permanently blocks future revisions
- the docs explicitly state one pending revision per lineage
- the docs explicitly state baseline = latest approved revision else original parent
- the docs still state revision-on-revision is not allowed

### 10.7 Final acceptance run
The implementation only passes final verification when all of these are true in one pass:
- feature tests for the revision flow pass
- stock values match the expected baseline-delta behavior in the sequential approval scenarios
- the new pending-conflict path is enforced
- docs and runtime behavior say the same thing

## 11. Implementation Notes / Non-Goals
- Do not convert the model into chained revisions.
- Do not change transaction type during a revision.
- Do not change list/show/detail response envelopes.
- Do not broaden this work into direct-correction or stock-opname redesign.

## 12. Acceptance Criteria
The change is complete when:
- multiple revisions can be submitted over time for the same original transaction lineage
- there is never more than one pending revision in a lineage
- each approval computes delta against the latest approved sibling or original parent if none exists
- stock remains unchanged on submit and reject
- stock is mutated exactly once on approve
- docs and tests both reflect the new sequential sibling model
