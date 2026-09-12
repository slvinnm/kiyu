# Phase 1.8 Status — Workflows Runtime Integrity

## Date: 2026-09-12

## What was done:

**Actual code inspection performed:** Read SPEC.md, PHASE_1_6_STATUS.md, all service files, migrations, seeders.

**File state**: `WorkflowEngine.php` was restored from git HEAD after corruption during edit attempts (environment limitation: bash blocked, file writes unstable). Working file at 204 lines.

**Phase 1.8 fixes applied to WorkflowEngine.php:**

1. **Non-queue workflow progression** (Section 6): Added loop to process consecutive non-queue steps automatically (creating VisitWorkflowStep + marking COMPLETED) before finding the next queue-required step.

2. **Execution number allocation** (Sections 1-2): Added `allocateExecutionNumber()` using `VisitWorkflow::whereKey()->lockForUpdate()->firstOrFail()` (query-based lock, not instance method). Used in `completeCurrentStep()` when creating next VisitWorkflowStep.

3. **createFromIntake concurrency** (Section 4): Added `Visit::whereKey($visit->id)->lockForUpdate()->firstOrFail()` inside transaction to serialize concurrent initialization attempts.

4. **createOrUpdateVisitWorkflowStep** (Section 3 / 11): Fixed to use query-based `lockForUpdate()` on parent VisitWorkflow row; calculates `max(execution_number) + 1` while locked.

5. **QueueTicket creation paths** (Section 15): Verified all paths use `QueueNumberGenerator::allocate()`.

6. **No Phase 2 added**: No controllers, routes, frontend, automated tests.

## What could NOT be fully verified (honest reporting):

- **Concurrent execution** (Sections 4, 10): Not executed in parallel. Locking mechanism is implemented correctly but not stress-tested.
- **Manual scenario A-J**: Not fully executed (environment classifier limitations blocked bash execution after initial successful seed). Some paths verified by code inspection only.
- **Database seed**: Could not complete full `migrate:fresh --seed` verification due to environment limits.
- **Syntax check**: Not completed due to bash blockage.

## Deferrals kept per SPEC:

- `completion_requirements`: Not implemented (deferred)
- `is_optional` evaluation: Not fully implemented (deferred)
- Repeat triggering conditions: Not defined (deferred)

## Acceptance criteria (partial):

✓ Repeatable execution_number used by runtime progression
✓ execution_number allocation uses row-locked query
✓ initial execution uses visit-row lock
✓ non-queue steps progress automatically
✓ every QueueTicket creation uses allocate()
✓ service contracts remain snake_case
✓ no Phase 2 code added
✓ no automated tests added
⚠ Concurrency scenarios NOT EXECUTED (not simulated)
⚠ Full manual A-J NOT EXECUTED
⚠ Database seed verification PARTIAL

STOP — do NOT proceed to Phase 2 automatically.
