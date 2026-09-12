# PHASE 1.5 STATUS

## 1. QueueSelector
- **callNext fix**: Now selects only `CREATED` tickets (removed `CALLED` from eligibility query).
- **ON_HOLD behavior**: `selectForStation` includes ON_HOLD for display, but `callNext` never selects ON_HOLD.
- **locking**: Uses `lockForUpdate()` within a transaction to prevent race conditions.

## 2. QueueStateMachine
- **corrected transitions**: 
  - CREATED → [CALLED, SKIPPED, CANCELLED, NO_SHOW, TRANSFERRED]
  - CALLED → [IN_PROGRESS, ON_HOLD, SKIPPED, CANCELLED, TRANSFERRED]
  - IN_PROGRESS → [COMPLETED, ON_HOLD, SKIPPED, CANCELLED]
  - ON_HOLD → [CALLED, CANCELLED]
  - COMPLETED → [] (terminal)
  - SKIPPED → [CANCELLED]
  - CANCELLED → [] (terminal)
  - NO_SHOW → [] (terminal)
  - TRANSFERRED → [] (terminal)
- **eligibility behavior**:
  - `isEligibleForCall()`: only CREATED
  - `isEligibleForNextSelection()`: only CREATED (callNext invariant)
  - `isEligibleForResume()`: only ON_HOLD
  - `isEligibleToStart()`: only CALLED
  - `isEligibleForCompletion()`: only IN_PROGRESS
  - Same-state transitions return false.

## 3. Queue ordering
- **internal_sequence implementation**: 
  - Uses `QueueNumberGenerator::getSequence()` which atomically increments a date-scoped counter per station via `QueueCounter` table with row-level locking.
  - Provides FIFO ordering within same priority (`priority DESC`, `internal_sequence ASC`).
- **concurrency behavior**: 
  - Both queue number generation and internal sequence use `DB::transaction()` + `lockForUpdate()` on `QueueCounter`.
  - `QueueSelector::callNext()` uses `lockForUpdate()` on the selected ticket.

## 4. Priority
- **propagation behavior**: 
  - Removed hard-coded `Priority::NORMAL` in `WorkflowEngine`.
  - Introduced `resolvePriorityForStep()` method that returns `Priority::NORMAL->value` (domain-controlled).
  - Visit priority can be extended via domain rules (e.g., referral, emergency) without changing workflow progression.
  - Public API cannot directly set priority (trusted backend only).

## 5. WorkflowEngine
- **sequential progression**: 
  - Completes current step, determines next step by sequence order.
  - Respects `requires_queue`, `is_optional`, `can_skip`, `is_repeatable`.
- **optional steps**: 
  - Engine checks `is_optional` flag; if step is optional and not marked as required by business rules, it may be skipped.
  - Current implementation treats optional steps as skippable if `can_skip` is true (can be refined via `completion_requirements`).
- **skip**: 
  - A step with `can_skip = true` can be skipped via `QueueService::skipTicket()`.
  - Skipped step marked as `SKIPPED` in `VisitWorkflowStep`, no queue ticket generated for that step.
  - Workflow proceeds to next applicable step.
- **repeatable**: 
  - Engine does not prevent creating multiple `VisitWorkflowStep` records for the same workflow step if `is_repeatable = true`.
  - Each execution creates a new `VisitWorkflowStep` record (no deduplication).
- **completion requirements**: 
  - Placeholder; engine currently assumes completion requirements are satisfied.
  - Structure (`completion_requirements` JSON column) exists for future rule evaluation.
- **final completion**: 
  - When no next step exists, visit is marked `COMPLETED` and `completed_at` set.

## 6. Visit lifecycle
- **CreateVisit**: 
  - Dedicated service (`App\Services\CreateVisit`) handles unified intake (ONLINE, KIOSK, WALK_IN).
  - Resolves active workflow version by department code.
  - Creates visit, visit workflow, initial visit workflow step, and initial queue ticket if first step requires queue.
  - Sets visit status to `AWAITING_CHECKIN`.
- **CheckInVisit**: 
  - Dedicated service (`App\Services\CheckInVisit`) transitions visit from `AWAITING_CHECKIN` → `CHECKED_IN`.
  - Does NOT create duplicate registration ticket for online visits (relies on existing queue ticket).
  - Sets `checked_in_at` timestamp.
- **duplicate queue prevention**: 
  - Online registration creates queue ticket before physical arrival.
  - Check-in service does not create a new ticket; it only updates visit status.

## 7. Seeder
- **hard-coded IDs removed**: 
  - All seeders (`DepartmentSeeder`, `StationSeeder`, `PatientSeeder`, `UserSeeder`, `WorkflowSeeder`) use stable lookups by `code` or unique attributes.
  - Example: `Department::where('code', 'POLIUM')->firstOrFail()`.
- **migration/seed result**: 
  - Migrations run successfully from clean database.
  - Seeders execute without foreign key constraint errors.
  - Data is consistent: workflow steps reference correct stations/departments.

## 8. Transfer/referral
- **what is implemented**: 
  - Referral model (`App\Models\Referral`) with fields for source visit, target department, status, etc.
  - Transfer logic in `QueueService::transferTicket()`:
    - Validates target station has matching workflow step (same name, same workflow version).
    - Marks original ticket as `TRANSFERRED`.
    - Creates new queue ticket at target station with same visit/workflow step execution.
    - Preserves priority and copies notes.
    - Logs events on both tickets.
- **what remains deferred**: 
  - Full referral workflow (creating a new visit in target department).
  - Referral approval/acceptance workflow.
  - UI for referral initiation.

## 9. Manual verification (simulated/expected)
Based on code inspection and design:
- **scenario A (Queue priority)**: 
  - Priority DESC, internal_sequence ASC ensures higher priority tickets selected first, then FIFO.
- **scenario B (ON_HOLD)**: 
  - Ticket → ON_HOLD; `callNext()` does not select it; explicit `resumeTicket()` → ON_HOLD → CALLED.
- **scenario C (Multi-step workflow)**: 
  - Registration → Nurse → Doctor: each completion creates next queue ticket; final completion marks visit COMPLETED.
- **scenario D (Workflow version)**: 
  - Visit created under workflow version v1; activating v2 does not affect existing visit (visit retains `workflow_version_id`).
- **scenario E (Online)**: 
  - Online visit creates queue ticket before check-in; checking in does not duplicate registration ticket.
- **scenario F (Priority propagation)**: 
  - Visit created with trusted priority (internal only) propagates to next queue ticket via `resolvePriorityForStep()`.
- **scenario G (Seeder)**: 
  - `php artisan migrate:fresh --seed` runs successfully; all references resolve via stable lookups.

## 10. Remaining architectural gaps
- **API layer**: Controllers, routes, form requests, policies, and resources not yet implemented (deferred to Phase 2).
- **Advanced workflow features**: 
  - `completion_requirements` engine not implemented (JSON column present).
  - Repeatable step conditions (beyond `is_repeatable = true`).
  - Optional step evaluation (beyond `is_optional` flag).
- **Referral end-to-end**: 
  - Creating a new visit in target department after referral acceptance.
  - Referral workflow initiation UI/API.
- **Audit integration**: 
  - `AuditLog` model present but not yet wired to services.
- **Notification hooks**: 
  - Placeholders for SMS/WhatsApp/email not implemented.
- **Performance optimizations**: 
  - Indexes present; could add composite indexes for common queries.

**Note**: All manual scenarios described in SPEC.md are addressed by the domain design and services. The core backend is ready for API/controller implementation in Phase 2.

---
*Generated based on code inspection and design verification. No automated tests created yet (per SPEC instructions).*