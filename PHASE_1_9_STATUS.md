# Phase 1.9 Status — Queue Mutation Concurrency (Pass 5 — Final)

## Date: 2026-09-12 (Pass 5 — interrupted resume complete)

## Continuation of Pass 5 (from previous session interruption)

Fixes applied to restore workflow/concurrency integrity (verified by inspection / syntax check; concurrency NOT EXECUTED):

- `WorkflowEngine::createFromIntake()` — station validation at `requires_queue` (line 66-68); initial step lookup kept at execution 1 (line 44-48) with allocator-based creation at line 57 (`allocateExecutionNumber()` with `lockForUpdate` query lock — verified syntax).
- `QueueNumberGenerator::allocate()` — rewritten to remove `DB::transaction()` wrapper; method is transaction-neutral (caller owns boundary via `DB::transaction()` in QueueService/WorkflowEngine). Row-level `lockForUpdate()` preserved. Syntax verified (`php -l` OK).
- `WorkflowEngine::allocateExecutionNumber()` — uses `VisitWorkflow::whereKey()->lockForUpdate()->firstOrFail()` then `VisitWorkflowStep::max('execution_number')`; query-lock verified by inspection.
- All 7 QueueService mutation locks verified present (`startTicket`, `holdTicket`, `resumeTicket`, `skipTicket`, `cancelTicket`, `transferTicket`, `completeTicket`).

## Pass 5 Status — Verified by Inspection / Syntax (NOT EXECUTED)

- `php -l` passes on `WorkflowEngine.php`, `QueueService.php`, `QueueNumberGenerator.php` (SYNTAX OK ALL)
- `QueueNumberGenerator::allocate()` contains no `DB::transaction()` wrapper (confirmed by Read)
- `WorkflowEngine::createFromIntake()` station validation present (`!$initialStep->station` throws LogicException)
- `QueueStateMachine::isEligibleForNextSelection()` uses `=== QueueStatus::CREATED` enum comparison (Pass 3)
- `QueueService::callNext()` retains `DB::transaction()` (selection + transition owned by service; QueueSelector has no inner transaction)
- No Phase 2 code added; no dependency changes

## NOT EXECUTED (honest reporting — unchanged from Pass 4)

Concurrent mutation scenarios A–G, full `migrate:fresh --seed`, manual A–J verification, concurrent `transferTicket()` (highest risk — NOT EXECUTED across all passes).

## Date: 2026-09-12 (Pass 4)

## Fix Applied

Every QueueService method that mutates a QueueTicket now acquires a row lock before reading status or applying state changes:

- `startTicket()` → `QueueTicket::whereKey()->lockForUpdate()->firstOrFail()` (line 84)
- `holdTicket()` → `QueueTicket::whereKey()->lockForUpdate()->firstOrFail()` (line 169)
- `resumeTicket()` → `QueueTicket::whereKey()->lockForUpdate()->firstOrFail()` (line 197)
- `skipTicket()` → `QueueTicket::whereKey()->lockForUpdate()->firstOrFail()` (line 228)
- `cancelTicket()` → `QueueTicket::whereKey()->lockForUpdate()->firstOrFail()` (line 264)
- `transferTicket()` → `QueueTicket::with(...)->whereKey()->lockForUpdate()->firstOrFail()` (line 306)
- `completeTicket()` → preserved existing `lockForUpdate()` (line 125)

## Transfer Behavior After Lock

The entire transfer transaction holds the source QueueTicket lock from selection through:
- eligibility validation
- source transition to TRANSFERRED
- target station lookup
- target workflow step lookup
- replacement QueueTicket creation
- CREATED event on replacement
- commit

A concurrent second transfer request for the same source will wait for the lock; upon acquiring it, it reads TRANSFERRED and fails eligibility, creating zero replacement tickets.

## Enum Consistency

`QueueStateMachine::isEligibleForNextSelection()` uses `=== QueueStatus::CREATED` (enum comparison), matching the corrected pattern from Pass 3.

## Transaction Ownership (unchanged from Pass 3)

- QueueService::callNext() → owns transaction (selection + transition)
- QueueSelector::callNext() → selection/lock only, no transaction
- QueueService::completeTicket() → owns transaction (completion + workflow progress)
- WorkflowEngine::completeCurrentStep() → caller transaction participant
- QueueStateMachine::apply() → transaction-neutral

## Verified by Code Inspection

- All 7 mutation methods contain `whereKey($ticketId)->lockForUpdate()`
- No mutation method uses `findOrFail()` without lock
- QueueService::transferTicket() includes visit/workflow load with lock (line 308)
- QueueNumberGenerator::allocate() syntax valid; getSequence() removed; no callers
- QueueStateMachine event ownership preserved
- No Phase 2 code added

## NOT EXECUTED (honest reporting)

- A: Concurrent startTicket()
- B: Concurrent holdTicket()
- C: Concurrent resumeTicket()
- D: Concurrent skipTicket()
- E: Concurrent cancelTicket()
- F: Concurrent transferTicket() (most critical — not executed)
- G: Concurrent completeTicket()
- H: Concurrent callNext()
- Full migrate:fresh --seed
- Manual A-J scenario verification

## Suggested Commit

`Fix: harden queue ticket mutation concurrency`
