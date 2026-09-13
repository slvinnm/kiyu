# KIYU Backend — Final Audit Specification for AI Agent

> **Purpose:** This document is an instruction/specification for an AI coding/review agent that will perform the **final backend audit** of KIYU.
>
> **Important:** Do not treat this document as a claim that the current code already satisfies these requirements. The agent must inspect the repository, verify each item against the actual implementation, run tests where appropriate, and report discrepancies.

---

# 1. Audit Objective

Perform a comprehensive final audit of the KIYU Laravel backend before frontend development.

The audit must answer four questions:

1. **Does the current backend architecture match the intended domain model?**
2. **Does every required API capability exist and work correctly?**
3. **Are state transitions, database invariants, authorization, and concurrency safe?**
4. **What must be fixed before the backend can be signed off?**

The agent must not simply rely on `php artisan test` passing. Passing tests are necessary but not sufficient.

The audit must inspect:

- architecture
- domain model
- migrations/schema
- models/relations
- enums/state machines
- services/domain logic
- controllers
- Form Requests
- Policies/authorization
- API Resources
- routes
- exception/error handling
- database constraints/indexes
- transactions and locks
- concurrency behavior
- idempotency
- audit/event logging
- automated tests
- CI configuration
- API completeness

---

# 2. Repository Context

Repository:

`https://github.com/slvinnm/kiyu`

Primary backend stack:

- Laravel
- PHP 8.5
- Eloquent
- Laravel Sanctum
- Pest
- Pint
- MySQL
- Inertia/React exists for frontend but frontend is **not the current audit target**

The backend must remain API-first.

Follow Laravel conventions:

- Form Requests
- Policies
- API Resources
- Eloquent
- domain services
- enums
- explicit state transitions
- DB transactions
- `lockForUpdate()` where concurrency requires it
- feature/integration tests

Do not introduce unnecessary abstractions merely for abstraction's sake.

---

# 3. Core Domain Model

The intended conceptual model is:

```text
Patient
   ↓
Visit
   ↓
WorkflowVersion
   ↓
WorkflowStep
   ↓
VisitWorkflow
   ↓
VisitWorkflowStep
   ↓
QueueTicket
   ↓
Station
```

Additional core entities:

```text
Department
QueueAcquisition
QueueCounter
QueueEvent
AuditLog
Referral
User
```

Important distinction:

```text
Department / Poli / Service
≠
Station / Computer / Service Counter
```

A Department may contain multiple Stations.

Example:

```text
General Department
 ├── Registration Station
 ├── Nurse Station
 └── Doctor Station
```

A `QueueTicket` is an execution of a specific workflow step, not the whole visit.

One Visit can have multiple QueueTickets over its lifecycle.

---

# 4. Intake Channels

There must be one unified workflow/queue engine for:

```text
ONLINE
KIOSK
WALK_IN
```

Do not create independent queue engines for each channel.

Expected architecture:

```text
ONLINE
  ↓
CreateOnlineVisit
  ↓
CreateVisit
  ↓
WorkflowEngine

KIOSK
  ↓
QueueAcquisitionService
  ↓
WorkflowEngine

WALK_IN
  ↓
RegisterReceptionVisit / CreateVisit
  ↓
WorkflowEngine
```

Verify that all channels eventually use the same workflow/queue lifecycle.

---

# 5. Workflow Model Requirements

## Workflow definition

The backend must support:

- Workflow
- WorkflowVersion
- WorkflowStep
- step sequence
- queue-required steps
- non-queue steps
- optional steps
- entry conditions
- completion requirements
- repeatable steps
- station assignment

## WorkflowVersion pinning

A Visit must be pinned to the WorkflowVersion used when the Visit was created.

Changing the current/active workflow later must not silently change an existing Visit's historical workflow.

## Active workflow invariant

For normal Visit creation, the application expects:

```text
Department
    ↓
exactly one active Workflow
    ↓
exactly one active WorkflowVersion
```

The agent must verify both:

1. application-level validation
2. concurrency/database integrity around workflow activation

---

# 6. Runtime Workflow Requirements

Runtime entities:

```text
VisitWorkflow
VisitWorkflowStep
```

Expected VisitWorkflow statuses:

```text
ACTIVE
COMPLETED
CANCELLED
```

Expected VisitWorkflowStep statuses may include:

```text
PENDING
IN_PROGRESS
COMPLETED
SKIPPED
CANCELLED
```

The agent must verify whether these statuses are sufficient for the actual queue lifecycle.

Do not automatically add new states without a business reason.

---

# 7. Queue State Machine

QueueStatus is expected to cover:

```text
CREATED
CALLED
IN_PROGRESS
COMPLETED
CANCELLED
SKIPPED
NO_SHOW
ON_HOLD
TRANSFERRED
```

Expected transition model:

```text
CREATED
 ├── CALLED
 ├── SKIPPED
 ├── CANCELLED
 ├── NO_SHOW
 └── TRANSFERRED

CALLED
 ├── IN_PROGRESS
 ├── ON_HOLD
 ├── SKIPPED
 ├── CANCELLED
 └── TRANSFERRED

IN_PROGRESS
 ├── COMPLETED
 ├── ON_HOLD
 ├── SKIPPED
 ├── CANCELLED
 └── TRANSFERRED

ON_HOLD
 ├── CALLED
 └── CANCELLED
```

Terminal states are expected to include:

```text
COMPLETED
CANCELLED
NO_SHOW
TRANSFERRED
```

The agent must verify every actual transition in `QueueStateMachine` and ensure no service bypasses the intended transition authority.

---

# 8. Cross-Entity Lifecycle Audit

This is one of the most important parts of the audit.

A QueueTicket state change must leave related runtime state consistent.

Audit the relationship:

```text
Visit
VisitWorkflow
VisitWorkflowStep
QueueTicket
QueueAcquisition
QueueEvent
AuditLog
```

For each operation below, document the expected final state of **every related entity**.

## Operations to audit

- create
- call
- start
- complete
- hold
- resume
- skip
- no-show
- cancel
- transfer
- referral
- workflow completion
- kiosk cancellation
- online check-in

The agent must identify any combination like:

```text
QueueTicket = CANCELLED
VisitWorkflowStep = IN_PROGRESS
```

or:

```text
QueueTicket = NO_SHOW
Visit = WAITING
```

and determine whether the combination is intentional or invalid.

Do not fix state inconsistencies by guessing. Document the intended invariant first, then implement it.

---

# 9. Queue Ordering Requirements

Queue ordering must be:

```text
priority DESC
internal_sequence ASC
```

Priority levels:

```text
NORMAL = 1
PRIORITY = 2
EMERGENCY = 3
```

Public patients must not arbitrarily assign emergency priority.

The agent must verify:

- queue selector ordering
- priority propagation
- transfer priority propagation
- repeated workflow ticket priority
- referral priority rules
- priority update behavior
- queue ordering under equal priority

---

# 10. Queue Number Requirements

`QueueNumberGenerator` should own queue number allocation.

Verify:

- station-specific prefix
- date-scoped sequence
- internal sequence
- counter locking
- uniqueness
- concurrency safety

Test conceptually:

```text
100 concurrent queue allocations
→ no duplicate queue number
→ no duplicate internal sequence for same station/date
```

---

# 11. Queue Concurrency Requirements

A station cannot call another ticket while an active ticket already exists.

Active station ticket states:

```text
CALLED
IN_PROGRESS
```

`callNext()` must:

1. lock the station
2. check active ticket
3. select the next CREATED ticket
4. transition it to CALLED
5. commit atomically

Verify race conditions around:

- callNext vs callNext
- callNext vs cancel
- callNext vs complete
- transfer vs callNext
- hold vs callNext

---

# 12. Idempotency Requirements

Kiosk queue acquisition supports an idempotency key.

Verify:

```text
same key + same department
→ same acquisition

same key + different department
→ conflict/rejection
```

Database must provide a unique constraint for the idempotency key.

Important:

`SELECT ... FOR UPDATE` cannot protect a row that does not yet exist.

Therefore the agent must verify the implementation is safe against:

```text
Request A → key not found
Request B → key not found
Request A → insert
Request B → insert
```

A database unique constraint is required as the final protection.

Test concurrent same-key requests.

---

# 13. Kiosk Flow

Expected flow:

```text
Kiosk
 ↓
QueueAcquisition
 ↓
Visit (patient_id nullable)
 ↓
initial WorkflowStep
 ↓
QueueTicket
 ↓
Reception registers patient
 ↓
Visit.patient_id populated
```

Required capabilities:

- department discovery
- queue acquisition
- idempotency
- patient-less Visit
- initial queue ticket
- reception registration
- cancellation
- lifecycle consistency

Potential lifecycle:

```text
ACQUIRED
REGISTERED
CANCELLED
EXPIRED
```

If expiration is required by the product, define its timeout and cleanup behavior explicitly.

---

# 14. Kiosk Cancellation Audit

Kiosk cancellation must be atomic.

Expected consistency target:

```text
QueueAcquisition → CANCELLED
Visit             → CANCELLED
VisitWorkflow     → CANCELLED
pending runtime steps → CANCELLED
active QueueTickets   → CANCELLED
QueueEvent            → CANCELLED event
```

The exact semantics must be verified against the business rules, but the agent must not allow an orphan active queue after cancellation.

Tests:

```text
cancel immediately after acquisition
cancel after CALLED
cancel after IN_PROGRESS
cancel after terminal state
```

Define whether progressed kiosk queues may still be cancelled.

---

# 15. Online Flow

Expected:

```text
Patient
 ↓
Online Visit
 ↓
AWAITING_CHECKIN
 ↓
initial registration queue already exists
 ↓
patient check-in
 ↓
CHECKED_IN
 ↓
WAITING
```

Check-in must not create another registration ticket.

Audit:

- creation
- duplicate active visit prevention
- ownership
- check-in
- duplicate check-in
- check-in race
- cancellation if supported
- terminal visit protection

---

# 16. Online Check-in Implementation

Search for all check-in services and endpoints.

If multiple services exist, compare them.

There must be one canonical business behavior.

Expected final behavior should be explicit:

```text
AWAITING_CHECKIN
→ CHECKED_IN
→ WAITING
```

or another explicitly defined business flow.

Do not allow two services to silently implement different lifecycle semantics.

---

# 17. Walk-in / Reception Flow

Expected:

```text
Reception
 ↓
Patient selection
 ↓
Create Visit
 ↓
WAITING
 ↓
QueueTicket
```

Audit:

- patient search
- walk-in Visit creation
- department authorization
- initial queue creation
- duplicate active visit policy
- patient ownership
- input validation

---

# 18. Transfer Requirements

Transfer means:

> Same workflow step, different valid station.

Expected:

```text
old QueueTicket
→ TRANSFERRED

new QueueTicket
→ CREATED

same VisitWorkflowStep
```

Target station must:

- be active
- differ from source station
- belong to valid department scope
- be allowed by current WorkflowStep

Audit all states:

```text
CREATED → transfer
CALLED → transfer
IN_PROGRESS → transfer
terminal → reject
```

Verify the semantic meaning of transferring an IN_PROGRESS ticket.

---

# 19. Referral Requirements

Referral is distinct from transfer.

Referral means:

```text
source Visit
 ↓
target Department / Service
```

Behavior:

1. validate source visit
2. validate patient
3. lock patient where concurrency requires it
4. validate target department
5. check existing active target Visit
6. join target Visit if appropriate
7. otherwise create target Visit
8. create Referral
9. audit action

Verify duplicate referral prevention.

Also verify referral concurrency.

---

# 20. Referral Priority Decision

The agent must identify whether this is defined by the SPEC.

Example:

```text
source Visit = NORMAL
referral = EMERGENCY
```

Possible rules:

- referral cannot increase priority
- clinical staff may escalate
- target priority inherits source priority
- explicit clinical policy applies

Do not invent a rule.

Mark as:

```text
BUSINESS DECISION REQUIRED
```

if no specification exists.

---

# 21. Authorization Requirements

Authorization must fail closed.

Validate the appropriate combination of:

```text
role
department scope
station scope
resource ownership
```

Required policy areas:

- Patient
- Visit
- Station
- QueueTicket
- Referral
- Priority
- Reception operations

Test every parameterized endpoint for IDOR.

For example:

```text
patient A requests patient B's visit
→ forbidden

staff station A requests station B ticket
→ forbidden

doctor department A requests department B protected action
→ forbidden
```

---

# 22. Patient Authentication Requirements

Expected API:

```text
POST /api/v1/patient/register
POST /api/v1/patient/login
GET  /api/v1/patient/me
POST /api/v1/patient/logout
```

If logout is intentionally absent, document the reason.

Verify:

- password hashing
- PATIENT role assignment
- Patient profile creation
- User ↔ Patient relation
- Sanctum token creation
- token revocation strategy
- missing Patient profile behavior
- brute-force throttling

---

# 23. Patient Profile Invariant

The following should be treated as an invariant unless the SPEC explicitly allows otherwise:

```text
User.role = PATIENT
→ Patient profile exists
```

Audit both application and database enforcement.

A PATIENT user with no Patient record must not cause a 500 on patient endpoints.

---

# 24. Public Queue Requirements

Endpoint:

```text
GET /api/v1/public/queues/{station}
```

Public response must not expose patient identity.

Never expose unnecessarily:

- name
- email
- phone
- national ID
- medical record number
- private clinical data

Audit resource serialization, not only service query logic.

---

# 25. Priority Management Requirements

Endpoint:

```text
PATCH /api/v1/priority/visits/{visit}
```

Required:

- enum validation
- authorization
- department scope
- state restrictions
- active ticket synchronization
- audit log
- no arbitrary patient escalation

Test:

- patient
- nurse
- doctor
- admin
- wrong department
- completed Visit
- cancelled Visit
- active queue
- repeated workflow

---

# 26. API Endpoint Audit Inventory

The agent must inspect the actual route file and produce a final table with:

```text
METHOD
PATH
AUTH
ROLE
POLICY
FORM REQUEST
CONTROLLER
SERVICE
RESOURCE
CURRENT STATUS
TEST STATUS
FINDINGS
```

Expected endpoint inventory to audit:

## Kiosk

```text
GET  /api/v1/kiosk/departments
POST /api/v1/kiosk/queue-acquisitions
```

## Public

```text
GET /api/v1/public/queues/{station}
```

## Patient auth

```text
POST /api/v1/patient/register
POST /api/v1/patient/login
GET  /api/v1/patient/me
POST /api/v1/patient/logout
```

## Patient visits

```text
GET  /api/v1/patient/visits
GET  /api/v1/patient/visits/{visit}
GET  /api/v1/patient/queue
POST /api/v1/patient/visits/{visit}/check-in
```

## Online

```text
POST /api/v1/online/visits
```

Potential endpoints, only if required by SPEC:

```text
GET  /api/v1/online/visits/{visit}
POST /api/v1/online/visits/{visit}/cancel
```

## Reception

```text
GET  /api/v1/reception/patients
POST /api/v1/reception/visits
POST /api/v1/reception/queue-acquisitions/{queueAcquisition}/register
```

Potential endpoint if cancellation is an explicit workflow:

```text
POST /api/v1/reception/queue-acquisitions/{queueAcquisition}/cancel
```

## Queue

```text
GET  /api/v1/queue/stations
POST /api/v1/queue/stations/{station}/call-next
POST /api/v1/queue/tickets/{queueTicket}/start
POST /api/v1/queue/tickets/{queueTicket}/complete
POST /api/v1/queue/tickets/{queueTicket}/hold
POST /api/v1/queue/tickets/{queueTicket}/resume
POST /api/v1/queue/tickets/{queueTicket}/skip
POST /api/v1/queue/tickets/{queueTicket}/no-show
POST /api/v1/queue/tickets/{queueTicket}/cancel
POST /api/v1/queue/tickets/{queueTicket}/transfer
```

## Priority

```text
PATCH /api/v1/priority/visits/{visit}
```

## Referral

```text
POST /api/v1/referrals/visits/{visit}
GET  /api/v1/referrals/{referral}
```

## Generic user

```text
GET /api/v1/user
```

The agent must distinguish:

- endpoint exists
- endpoint is authorized correctly
- endpoint's service logic is correct
- endpoint has sufficient tests
- endpoint is business-complete

---

# 27. API Resource Audit

Inspect every public API Resource.

Verify:

- no accidental sensitive fields
- stable field names
- enums serialized intentionally
- timestamps serialized consistently
- relations are explicit
- no direct model leakage
- pagination is consistent
- nested resources do not explode payload size

Special attention:

```text
PatientResource
VisitResource
QueueTicketResource
PatientQueueTicketResource
QueueAcquisitionResource
ReferralResource
User response
```

---

# 28. Error Contract Audit

Define one predictable API error structure.

Recommended shape:

```json
{
  "success": false,
  "message": "...",
  "errors": {}
}
```

Audit these categories:

```text
401 Unauthorized
403 Forbidden
404 Not Found
409 Conflict
422 Validation Error
429 Too Many Requests
500 Internal Server Error
```

Business conflicts should not be accidentally returned as generic 500 errors.

The agent must identify places where `LogicException`, `firstOrFail`, `ValidationException`, policy failures, and domain conflicts produce inconsistent JSON.

---

# 29. Database Constraint Audit

Inspect all migrations and produce a table:

```text
TABLE
COLUMN(S)
TYPE
NULLABLE
FOREIGN KEY
UNIQUE
INDEX
ON DELETE
ON UPDATE
DOMAIN PURPOSE
STATUS
```

Verify at least:

```text
patients.user_id
visits.patient_id
visits.department_id
visits.workflow_version_id
queue_tickets.visit_id
queue_tickets.visit_workflow_step_id
queue_tickets.station_id
visit_workflows.visit_id
visit_workflows.workflow_version_id
visit_workflow_steps.visit_workflow_id
visit_workflow_steps.workflow_step_id
queue_acquisitions.visit_id
queue_acquisitions.department_id
queue_acquisitions.idempotency_key
```

---

# 30. Required Unique Constraints Audit

Verify whether these domain invariants need DB constraints:

```text
Department.code
Station.code
QueueAcquisition.idempotency_key
VisitWorkflow(visit_id, workflow_version_id)
VisitWorkflowStep(visit_workflow_id, workflow_step_id, execution_number)
```

Do not add constraints blindly. Confirm semantics from actual code/spec.

---

# 31. Transaction Audit

Inspect every mutating service.

For each operation, document:

```text
TRANSACTION?
LOCKED RECORDS?
ORDER OF LOCKS?
POSSIBLE RACE?
ROLLBACK SAFE?
EXTERNAL SIDE EFFECT?
```

Mandatory transaction review:

- queue acquisition
- kiosk registration
- kiosk cancellation
- online visit creation
- online check-in
- walk-in registration
- callNext
- start
- complete
- hold
- resume
- skip
- no-show
- cancel
- transfer
- referral
- priority update

---

# 32. Deadlock / Lock Ordering Audit

Do not only verify `lockForUpdate()` exists.

Check that different code paths do not lock the same entities in conflicting order.

Example:

```text
Path A:
Station → Ticket → Visit

Path B:
Visit → Ticket → Station
```

This can cause deadlocks.

Document lock ordering for the main queue operations.

---

# 33. Query / N+1 Audit

Inspect:

- queue display
- patient queue
- station list
- reception patient search
- referrals
- visit resources

Check for N+1 queries around:

```text
Visit
QueueTicket
Station
WorkflowStep
Department
Patient
```

Use eager loading where it materially improves performance.

Do not add eager loading everywhere without evidence.

---

# 34. Rate Limiting Audit

Expected baseline:

```text
Kiosk endpoints       → 60/min
Public queue          → 120/min
Patient register/login→ 10/min
```

Verify:

- route actually uses throttle middleware
- limits are documented
- responses return 429
- authenticated sensitive operations have appropriate protection

---

# 35. Audit/Event Logging

Verify all security-sensitive and business-important mutations are auditable.

At minimum consider:

```text
patient registered
online visit created
kiosk acquisition created
kiosk acquisition registered
kiosk acquisition cancelled
queue called
queue started
queue completed
queue skipped
queue no-show
queue cancelled
queue transferred
priority changed
referral created
workflow cancelled
```

Audit records should contain enough context for investigation without storing unnecessary sensitive data.

---

# 36. Testing Requirements

The agent must inspect the current test suite and create a coverage matrix.

Known high-value test areas:

```text
AuthorizationBoundaryTest
QueueConcurrencyTest
QueueAcquisitionLifecycleTest
TransferReferralTest
VisitPriorityTest
WorkflowEngineTest
```

Required additional scenarios where absent:

## Idempotency

```text
same key
same department
same result
```

```text
same key
different department
reject
```

```text
concurrent same key
one acquisition
```

## Workflow

```text
concurrent VisitWorkflow creation
```

```text
concurrent repeat execution
```

## Lifecycle

```text
cancel
no-show
hold
resume
transfer
```

## Race conditions

```text
complete vs cancel
callNext vs cancel
transfer vs complete
check-in vs cancel
priority vs callNext
referral vs referral
```

---

# 37. CI Audit

Inspect workflow files.

Verify CI executes at least:

- PHP dependency installation
- Laravel environment setup
- migrations/database preparation
- Pint check
- tests
- frontend build if required by repository

PHPStan/type checking may be optional according to project policy. Do not automatically reintroduce it if it is intentionally removed from CI.

Do not state CI is green unless the actual latest run is verified.

---

# 38. Production Configuration Audit

Inspect:

- `.env.example`
- `config/*`
- Sanctum configuration
- cache/session/queue configuration
- database settings
- CORS
- rate limiting
- logging
- debug configuration

Verify that no secret or credential is committed.

---

# 39. Security Audit

Minimum review:

- IDOR
- role escalation
- department scope bypass
- station scope bypass
- public queue information leakage
- mass assignment
- validation bypass
- token handling
- rate limiting
- exception information leakage
- SQL injection through dynamic filtering
- insecure direct model serialization
- race-condition privilege escalation

Do not expose:

```text
password
password hashes
Sanctum token material
national ID unnecessarily
private clinical information
```

---

# 40. Final Audit Output Required from the AI Agent

The agent must return a structured final report.

## A. Executive summary

Explain:

- overall backend readiness
- biggest risks
- whether frontend work should start

## B. Requirement matrix

Use:

| Area | Requirement | Status | Evidence | Severity | Action |
|---|---|---|---|---|---|

Status values:

```text
PASS
PARTIAL
FAIL
NOT IMPLEMENTED
BUSINESS DECISION
```

## C. Endpoint matrix

Use:

| Method | Endpoint | Auth | Role/Scope | Request | Controller | Service | Resource | Test | Status | Findings |
|---|---|---|---|---|---|---|---|---|---|---|

## D. State/lifecycle matrix

Use:

| Operation | Visit | VisitWorkflow | VisitWorkflowStep | QueueTicket | QueueAcquisition | Event/Audit | Status |
|---|---|---|---|---|---|---|---|

## E. Database matrix

Use:

| Table | Constraint | Expected invariant | Exists | Risk | Action |
|---|---|---|---|---|---|

## F. Findings

For every defect:

```text
ID
Severity
Title
Description
Business impact
Technical impact
File(s)
Line(s), if available
Related model/service/controller
Why current implementation is unsafe
Recommended fix
Required tests
Dependencies
Phase
```

Example:

```text
ID: F-001
Severity: P0
Title: Queue cancellation leaves runtime workflow active
Files:
- app/Services/QueueService.php
- app/Services/WorkflowEngine.php
Impact:
...
Fix:
...
Tests:
...
Phase:
1 — Lifecycle consistency
```

## G. Final phased plan

Group findings into:

```text
PHASE 0 — BLOCKERS
PHASE 1 — LIFECYCLE CONSISTENCY
PHASE 2 — DATABASE INTEGRITY
PHASE 3 — CONCURRENCY HARDENING
PHASE 4 — API CONTRACT
PHASE 5 — BACKEND SIGN-OFF
PHASE 6 — FRONTEND
```

---

# 41. Phase Definition

## Phase 0 — BLOCKERS

Must fix before considering backend stable:

- schema/service mismatch
- broken migrations
- severe authorization bypass
- cross-entity state corruption
- critical concurrency duplication

## Phase 1 — Lifecycle Consistency

Fix:

- cancel
- no-show
- hold
- resume
- transfer
- check-in
- kiosk lifecycle
- workflow completion

Goal:

> Every domain operation leaves a valid state across all related runtime entities.

## Phase 2 — Database Integrity

Fix:

- FKs
- unique constraints
- indexes
- activation invariants
- runtime uniqueness

Goal:

> The database itself rejects impossible duplicate/inconsistent states where feasible.

## Phase 3 — Concurrency

Fix:

- idempotency races
- workflow races
- queue races
- referral races
- cancellation/complete races

Goal:

> Concurrent requests resolve deterministically.

## Phase 4 — API Contract

Fix:

- response structure
- error status codes
- resources
- pagination
- authentication lifecycle
- endpoint completeness

Goal:

> Frontend can consume the backend through a stable contract.

## Phase 5 — Backend Sign-off

Required:

```text
all P0 closed
all P1 closed or explicitly accepted
all critical tests passing
CI green
migrate:fresh successful
schema reviewed
endpoint inventory frozen
API contract documented
```

## Phase 6 — Frontend

Frontend should begin only after Phase 5.

---

# 42. Backend Sign-off Checklist

Before reporting `BACKEND READY`, all applicable boxes must be checked:

### Architecture

- [ ] unified queue engine verified
- [ ] workflow separation verified
- [ ] WorkflowVersion pinning verified
- [ ] transfer/referral separation verified

### Authorization

- [ ] every endpoint reviewed
- [ ] IDOR tests complete
- [ ] role scope complete
- [ ] department scope complete
- [ ] station scope complete

### Lifecycle

- [ ] create
- [ ] call
- [ ] start
- [ ] complete
- [ ] hold
- [ ] resume
- [ ] skip
- [ ] no-show
- [ ] cancel
- [ ] transfer
- [ ] referral
- [ ] kiosk cancellation
- [ ] online check-in

### Database

- [ ] migrations consistent with models/services
- [ ] foreign keys verified
- [ ] unique constraints verified
- [ ] indexes verified
- [ ] idempotency constraint verified
- [ ] runtime uniqueness verified

### Concurrency

- [ ] callNext race
- [ ] idempotency race
- [ ] workflow race
- [ ] repeat execution race
- [ ] referral race
- [ ] complete/cancel race
- [ ] transfer/complete race
- [ ] check-in race

### API

- [ ] route inventory complete
- [ ] resources reviewed
- [ ] error contract finalized
- [ ] status codes consistent
- [ ] pagination documented
- [ ] token lifecycle documented

### Tests

- [ ] feature tests complete
- [ ] integration tests complete
- [ ] concurrency tests complete
- [ ] migration fresh test complete
- [ ] CI green

### Security

- [ ] IDOR checked
- [ ] mass assignment checked
- [ ] public information leakage checked
- [ ] token handling checked
- [ ] throttling checked
- [ ] sensitive serialization checked

---

# 43. Final Agent Instruction

Do not blindly modify everything you find.

For every issue:

1. inspect the actual code
2. identify the invariant/business rule
3. confirm whether the issue is truly a defect
4. locate the smallest correct fix
5. add/adjust tests
6. run the relevant tests
7. run the complete backend suite
8. report the exact result

Do not invent business rules.

Where the specification is unclear, mark:

```text
BUSINESS DECISION REQUIRED
```

Do not silently choose a behavior that changes the product domain.

Prefer:

```text
smallest correct fix
+
explicit invariant
+
feature test
+
concurrency test when relevant
```

over broad rewrites.

The final output must clearly distinguish:

```text
ALREADY CORRECT
NEEDS FIX
MISSING
UNCLEAR / BUSINESS DECISION
OUT OF SCOPE
```

The agent must not declare the backend production-ready until the Phase 0–5 sign-off criteria are satisfied or every exception is explicitly documented and accepted.
