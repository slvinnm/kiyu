# Phase 1.9 Status — Concurrency & Workflow Execution Integrity (Pass 3 — Transaction Boundaries)

## Date: 2026-09-12 (Pass 3)

## Changes from Pass 2

Pass 2 restored WorkflowEngine (visit lock, allocator, non-queue progression). Pass 3 fixes remaining transaction-boundary and concurrency issues.

## Actual Source Changes (verified)

1. QueueSelector::callNext() — removed DB::transaction() wrapper; selection only performs row lock.
2. QueueService::callNext() — retains DB::transaction(); owns select + CREATED→CALLED transition (single transaction boundary).
3. WorkflowEngine::completeCurrentStep() — removed DB::transaction() wrapper; participates in QueueService::completeTicket() transaction.
4. WorkflowEngine::createOrUpdateVisitWorkflowStep() — uses only allocator (no double manual lock); documents: must be called inside active transaction.
5. WorkflowEngine::allocateExecutionNumber() — no inner DB::transaction(); relies on caller transaction.
6. QueueStateMachine::isEligibleForNextSelection() — fixed enum comparison (`=== QueueStatus::CREATED` not `.value`).
7. QueueNumberGenerator::allocate() — retains DB::transaction() (all callers: createFromIntake, completeCurrentStep, QueueService::transferTicket are all inside transactions; own transaction is safe and preserves retry/atomicity).
8. QueueNumberGenerator.php — fixed syntax error (extra closing brace removed); `getSequence()` remains removed.

## Transaction Ownership After Fix

QueueService::callNext() → owns select + transition
QueueSelector::callNext() → selection + lock only (no transaction)
QueueService::completeTicket() → owns lock + complete + workflow progress
WorkflowEngine::completeCurrentStep() → participates in caller transaction
WorkflowEngine::allocateExecutionNumber() → participates in caller transaction (no inner transaction)
createOrUpdateVisitWorkflowStep() → participates in caller transaction (contract: must be called inside active DB transaction)
QueueNumberGenerator::allocate() → owns its own transaction (atomic counter increment + retry; callers are all transactional)

## Verification Performed

- `php -l` passed on: QueueSelector.php, QueueService.php, WorkflowEngine.php, QueueStateMachine.php, QueueNumberGenerator.php
- `grep` confirmed QueueSelector has no DB::transaction()
- `grep` confirmed completeCurrentStep() has no inner DB::transaction()
- `grep` confirmed QueueStateMachine uses enum comparison (`=== QueueStatus::CREATED`)
- `grep` confirmed QueueNumberGenerator.php syntax valid (passes `php -l`)
- `grep` confirmed getSequence() removed; no app-level references
- Code inspection: all VisitWorkflowStep::create() paths use allocator; all execution_number runtime assignments central

## Not Executed (honest)

- Concurrent callNext() (scenario A): NOT EXECUTED
- Concurrent completeTicket() (scenario B): NOT EXECUTED
- Concurrent initialization (scenario C): NOT EXECUTED
- Concurrent execution-number allocation (scenario D): NOT EXECUTED
- Concurrent queue-number allocation (scenario E): NOT EXECUTED
- Full migrate:fresh --seed: NOT EXECUTED
- Database constraint violation stress testing: NOT EXECUTED
- Full A-J manual scenarios: NOT EXECUTED

These remain NOT EXECUTED; no claims of passing concurrent tests are made.

## Stop

Do NOT proceed to Phase 2. Phase 1.9 corrective pass 3 completes the transaction-boundary and concurrency fixes in code structure only.
