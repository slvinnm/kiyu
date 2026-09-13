# KIYU APP — Engineering Context / Handoff

## Project Overview

Repository: `https://github.com/slvinnm/kiyu`

Primary branch: `main`

Stack:
- Laravel
- PHP 8.5
- Laravel Sanctum
- Eloquent ORM
- Pest
- Pint
- MySQL

Current priority:

> Backend/API-first. Frontend is not the current focus.

The application is a medical-facility / clinic queue management system. Architecture should follow Laravel conventions, Eloquent, Form Requests, Policies, API Resources, domain services, enums, transactions, explicit business logic, and concurrency-safe operations.

---

## Core Business Architecture

Main domain relationship:

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

Important distinction:

```text
Department / Poli / Service
≠
Station / Computer / Service Counter
```

A department can have multiple stations, for example:

```text
General Department
 ├── Nurse Station
 └── Doctor Station
```

A `QueueTicket` represents one workflow-step execution, not the entire Visit.

One Visit can generate multiple QueueTickets as the workflow progresses.

---

## Queue Architecture

There is one unified queue/workflow engine for:

- `ONLINE`
- `KIOSK`
- `WALK_IN`

Do not create separate queue engines for each intake channel.

Queue numbers identify tickets, not patients.

Example:

```text
Patient A
  Registration → A001
  Nurse        → T005
  Doctor       → C002
```

Queue ordering:

```text
priority DESC
internal_sequence ASC
```

Priority:

```text
NORMAL = 1
PRIORITY = 2
EMERGENCY = 3
```

Public users must not arbitrarily choose emergency priority.

---

## Queue State Machine

Current queue states:

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

Transitions:

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

`ON_HOLD` must not automatically resume through `callNext()`. Resume must be explicit.

`QueueStateMachine` also creates `QueueEvent` records for state transitions.

---

## QueueService

`App\Services\QueueService` owns queue lifecycle:

- `callNext()`
- `startTicket()`
- `completeTicket()`
- `holdTicket()`
- `resumeTicket()`
- `skipTicket()`
- `markNoShow()`
- `cancelTicket()`
- `transferTicket()`

QueueService delegates workflow progression to `WorkflowEngine`.

Completion flow:

```text
QueueService
    ↓
QueueStateMachine
    ↓
WorkflowEngine.completeCurrentStep()
    ↓
next workflow step / next queue / workflow completion
```

### Station concurrency rule

A station cannot call another ticket while it already has an active ticket.

Active states for this purpose:

```text
CALLED
IN_PROGRESS
```

`callNext()` locks the Station row and checks whether the station already has an active QueueTicket. If so, it throws `ValidationException`.

Expected behavior:

```text
Station A:
Ticket 1 = CALLED

callNext(Station A)
→ ValidationException

Ticket 2 remains CREATED
```

Same-ticket operations use `lockForUpdate()`.

Queue number counters also use row locking.

---

## QueueSelector

`QueueSelector` selects only `CREATED` tickets.

Ordering:

```text
priority DESC
internal_sequence ASC
```

Selection is lock-protected to avoid selecting the same ticket concurrently.

---

## Queue Number Generation

`QueueNumberGenerator` allocates station-specific queue numbers using date-scoped counters.

It returns:
- queue number
- internal sequence

Counter records are locked during allocation.

---

## Transfer vs Referral

These concepts must remain separate.

### Transfer

Transfer means:

> Same workflow step, different valid station.

Example:

```text
Doctor Station A
       ↓ transfer
Doctor Station B
```

Old ticket becomes `TRANSFERRED`, then a replacement `CREATED` ticket is generated for the target station.

Target station must:
- be active
- be different from source
- belong to the same department
- be allowed by the current WorkflowStep

Implemented in `QueueService::transferTicket()`.

### Referral

Referral means:

> Patient is referred to another department/service.

Referral can create a target Visit or join an existing active target Visit.

Referral must not unnecessarily create duplicate target visits or queues.

---

## WorkflowEngine

`WorkflowEngine` is the central workflow progression service.

Responsibilities:
- resolve pinned WorkflowVersion
- create VisitWorkflow
- create runtime VisitWorkflowStep
- evaluate entry conditions
- evaluate completion requirements
- support optional steps
- support repeatable steps
- create QueueTicket for queue-required steps
- progress through non-queue steps
- complete a Visit when workflow finishes
- resolve stations

Existing visits must remain pinned to their WorkflowVersion.

Changing an active workflow/version must not silently change existing visits.

---

## Workflow Invariant

Current architecture expects:

```text
Department
    ↓
exactly ONE active Workflow
    ↓
exactly ONE active WorkflowVersion
```

`CreateVisit` and `QueueAcquisitionService` were previously inconsistent here. This was fixed so both enforce the invariant.

Expected failures:

```text
0 active workflows
→ LogicException

>1 active workflow
→ LogicException

0 active workflow versions
→ LogicException

>1 active workflow versions
→ LogicException

workflow version has no steps
→ LogicException

initial QueueTicket cannot be created
→ LogicException
```

---

## CreateVisit

Service: `App\Services\CreateVisit`

Signature:

```php
handle(
    int $patientId,
    string $departmentCode,
    IntakeChannel $intakeChannel,
    ?int $priority = null
)
```

Flow:
1. Load Patient
2. Load active Department
3. Enforce exactly one active Workflow
4. Enforce exactly one active WorkflowVersion
5. Require at least one WorkflowStep
6. Create Visit
7. Use WorkflowEngine to create the initial QueueTicket
8. Fail if initial ticket cannot be created

Initial Visit status:

```text
ONLINE
→ AWAITING_CHECKIN

KIOSK
→ WAITING

WALK_IN
→ WAITING
```

---

## Online Flow

```text
Patient
    ↓
Online Visit
    ↓
Visit = AWAITING_CHECKIN
    ↓
Initial registration queue already exists
    ↓
Patient physically checks in later
```

Check-in must not create a duplicate registration QueueTicket.

---

## Patient Authentication

Authentication uses Laravel Sanctum.

Roles:

```text
ADMIN
RECEPTIONIST
NURSE
DOCTOR
PHARMACY
LAB
STAFF
PATIENT
```

Patient relationship:

```text
patients.user_id
    ↓
users.id
```

`User` has a `patient()` relation.

`Patient` has a `user()` relation.

A missing FK was discovered during the integrity audit. A migration was added so:

```text
patients.user_id
    →
users.id
```

with `ON DELETE SET NULL`.

---

## Patient Authorization

Patient-facing endpoints fail closed.

Relevant endpoints:

```text
GET  /api/v1/patient/me
GET  /api/v1/patient/visits
GET  /api/v1/patient/queue
POST /api/v1/online/visits
```

Only `UserRole::PATIENT` can use them.

Patient must also have a Patient profile.

---

## VisitPolicy

Current intended rules:

- PATIENT: view own visits only and check in own visits only
- ADMIN: can change priority
- DOCTOR/NURSE: can change priority within their department
- Other roles: cannot change visit priority

Patient ownership uses:

```php
$user->patient?->id === $visit->patient_id
```

---

## QueueTicketPolicy

Queue tickets are scoped by station.

Operational roles:

- RECEPTIONIST
- NURSE
- DOCTOR
- PHARMACY
- LAB
- STAFF

Admin bypasses.

Non-admin operational users must belong to the same station as the ticket.

PATIENT must not manage queue tickets.

---

## StationPolicy

Rules:

```text
ADMIN
→ allowed

Operational staff
→ only own station

PATIENT
→ denied

Other station
→ denied
```

---

## Referral Authorization

Referral creation:

```text
ADMIN
DOCTOR
NURSE
```

Non-admin users must be in source-department scope.

Referral view:

```text
ADMIN
→ allowed

DOCTOR/NURSE
→ allowed when their department is source OR target

PATIENT
→ denied

Other staff
→ denied
```

---

## ReferralService

Current flow:

1. Lock source Visit
2. Require registered patient
3. Require source Visit to be active
4. Lock Patient
5. Validate active target Department
6. Target Department must differ from source
7. Look for existing active target Visit for same patient/department
8. Join existing target Visit when appropriate
9. Otherwise create target Visit
10. Create Referral
11. Create AuditLog

Active target Visit statuses:

```text
AWAITING_CHECKIN
CHECKED_IN
WAITING
IN_PROGRESS
```

Target Visit must have an active QueueTicket:

```text
CREATED
CALLED
IN_PROGRESS
ON_HOLD
```

Duplicate active referrals to the same target Visit are rejected.

Patient row locking was added to serialize concurrent referral creation for the same patient.

---

## Referral Priority — Business Rule Still Open

`ReferralService` can receive a priority value.

Potentially:

```text
source = NORMAL
referral = EMERGENCY
```

This is an unresolved business rule.

Do not silently decide it.

Possible policies:
1. Referral priority cannot exceed source priority.
2. DOCTOR/NURSE may explicitly escalate priority.
3. Another explicit clinical/business rule.

Confirm against the SPEC before enforcing one.

---

## Kiosk Flow

Kiosk uses `QueueAcquisition`, not `TemporaryPatient`.

Flow:

```text
Kiosk
 ↓
QueueAcquisition
 ↓
Visit (patient_id nullable)
 ↓
initial WorkflowStep
 ↓
Registration QueueTicket
 ↓
Reception registers Patient
 ↓
Visit.patient_id populated
```

`QueueAcquisitionService` enforces:
- exactly one active Workflow
- exactly one active WorkflowVersion

---

## Kiosk Idempotency

Kiosk queue acquisition supports an optional `idempotency_key`.

Same key is locked and reused instead of generating another Visit.

Same key cannot be reused for another Department.

---

## Kiosk Registration

Endpoint:

```text
POST /api/v1/reception/queue-acquisitions/{queueAcquisition}/register
```

Authorization:
- ADMIN
- or RECEPTIONIST within same Department

Registration:
1. lock acquisition
2. require `ACQUIRED`
3. lock Visit
4. ensure kiosk intake channel
5. attach Patient to Visit
6. set `registered_by`
7. mark acquisition `REGISTERED`

No duplicate QueueTicket is created.

---

## Kiosk Cancellation — CURRENT OUTSTANDING BUG

Current `QueueAcquisitionService::cancel()` only marks:

```text
QueueAcquisition = CANCELLED
```

The underlying Visit/QueueTicket/VisitWorkflowStep may remain active.

Potential inconsistent state:

```text
QueueAcquisition = CANCELLED
Visit             = WAITING
QueueTicket       = CREATED
```

Required future fix:

```text
QueueAcquisition CANCELLED
+
Visit CANCELLED
+
initial VisitWorkflowStep CANCELLED
+
active QueueTicket CANCELLED
```

All changes should happen in one DB transaction.

QueueEvent/AuditLog handling should remain consistent with existing conventions.

Add feature tests proving no active/orphan queue remains after kiosk cancellation.

---

## Public Queue Display

Endpoint:

```text
GET /api/v1/public/queues/{station}
```

Intentionally unauthenticated.

Response must never expose patient identity.

---

## Public/Kiosk Abuse Protection

Intended throttling:

```text
Kiosk acquisition/departments
→ 60/min

Public queue display
→ 120/min

Patient registration/login
→ 10/min
```

Verify actual route declarations when continuing if needed.

---

## API Bootstrap Bug

A real bug was discovered during authorization tests.

`routes/api.php` existed, but `bootstrap/app.php` did not register it.

Correct routing configuration must include:

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

Without this, API endpoint tests returned `404` instead of testing authorization.

This was fixed in PR #5.

---

## API Routes

```text
GET  /api/v1/kiosk/departments
POST /api/v1/kiosk/queue-acquisitions

GET  /api/v1/public/queues/{station}

POST /api/v1/patient/register
POST /api/v1/patient/login

GET  /api/v1/patient/me
GET  /api/v1/patient/visits
GET  /api/v1/patient/visits/{visit}
GET  /api/v1/patient/queue
POST /api/v1/patient/visits/{visit}/check-in

POST /api/v1/online/visits

POST /api/v1/reception/queue-acquisitions/{queueAcquisition}/register

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

PATCH /api/v1/priority/visits/{visit}

POST /api/v1/referrals/visits/{visit}
GET  /api/v1/referrals/{referral}

GET /api/v1/user
```

---

## `/api/v1/user`

Current endpoint:

```php
Route::get('/user', function (Request $request) {
    return $request->user();
});
```

This has not yet received a deep API-contract audit.

Potential future improvement:
- keep it
- replace with role-specific profile resources
- verify it exposes no unintended internal fields

---

## Authorization Test Suite

File:

`tests/Feature/AuthorizationBoundaryTest.php`

Covers:
- patient denied internal station endpoint
- staff denied online visit
- staff denied patient profile
- queue ticket station authorization
- station authorization
- patient visit ownership
- referral authorization
- visit priority authorization

### Test fixture lesson

These are problematic:

```php
new Station(['id' => 10])
new Patient(['id' => 10])
```

because primary keys are not mass assignable.

Correct:

```php
$station = new Station;
$station->setAttribute('id', 10);
```

and:

```php
$patient = new Patient;
$patient->setAttribute('id', 10);
```

This was a test fixture bug, not a production authorization bug.

---

## Latest Known Test Result Before PR #5

User reported:

```text
6 failed, 16 passed
59 assertions
```

Failures:
- AuthorizationBoundaryTest: internal station endpoint
- AuthorizationBoundaryTest: online visit endpoint
- AuthorizationBoundaryTest: patient profile endpoint
- AuthorizationBoundaryTest: station authorization
- AuthorizationBoundaryTest: patient visit authorization
- QueueConcurrencyTest: active station ticket

Other test groups were passing:
- TransferReferralTest
- VisitPriorityTest
- WorkflowEngineTest
- second QueueConcurrencyTest

---

## Latest Fix PR

Latest PR:

`PR #5 — fix: repair authorization routes and queue concurrency guard`

URL:

`https://github.com/slvinnm/kiyu/pull/5`

Branch:

`fix/authorization-concurrency-tests`

Changes:
1. Register `routes/api.php` in `bootstrap/app.php`
2. Fix authorization test fixtures
3. Add active-ticket guard to `QueueService::callNext()`

At the time this context was prepared, CI for the latest commit was observed as queued.

Do not claim the full suite is green until CI actually reports success.

---

## Earlier Integrity/Safety PR

Earlier PR:

`PR #3 — fix: enforce integrity and workflow invariants`

Main changes:
- add `patients.user_id -> users.id` foreign key
- enforce exactly one active Workflow
- enforce exactly one active WorkflowVersion
- require workflow steps
- require initial QueueTicket
- add API/public endpoint throttling as part of the production-safety audit

---

## Important Existing Tests

```text
tests/Feature/AuthorizationBoundaryTest.php
tests/Feature/QueueConcurrencyTest.php
tests/Feature/TransferReferralTest.php
tests/Feature/VisitPriorityTest.php
tests/Feature/WorkflowEngineTest.php
```

### Workflow tests already covered

`WorkflowEngineTest` covers:
- basic Registration → Nurse → Doctor → COMPLETED progression
- optional/conditional steps
- repeatable steps
- completion requirements
- explicit skip

### Transfer/referral tests already covered

`TransferReferralTest` covers:
- transfer only to allowed WorkflowStep station
- reject transfer outside current WorkflowStep
- referral joins existing target Visit
- reject duplicate active referral
- reject referral from completed Visit

### Priority tests already covered

`VisitPriorityTest` covers:
- authorized clinical staff can change priority
- priority change is audited
- other department staff rejected

---

## Authorization Philosophy

Authorization must fail closed.

Sensitive actions should validate the appropriate combination of:

```text
role
+
department scope
+
station scope
+
resource ownership
```

Avoid IDOR.

Do not trust user-supplied IDs without policy/domain validation.

---

## Controller Philosophy

Keep controllers thin:

```text
Controller
   ↓
FormRequest
   ↓
Policy / authorization
   ↓
Domain Service
   ↓
Model / WorkflowEngine
   ↓
API Resource
```

Complex domain logic belongs in services/state machines, not controllers.

---

## Transaction Philosophy

Queue/workflow mutations should use:

```php
DB::transaction(...)
```

Relevant records should use:

```php
lockForUpdate()
```

when concurrent requests could create duplicate or inconsistent state.

---

## Current Outstanding Work

Recommended order:

### P0 — Verify PR #5 CI

First inspect the latest GitHub Actions result.

Do not assume all tests pass.

### P0 — Fix Kiosk Cancellation Lifecycle

Make cancellation atomic across:

```text
QueueAcquisition
Visit
VisitWorkflowStep
QueueTicket
```

and add tests.

### P0 — Define Referral Priority Rule

Confirm whether referrals can escalate priority.

### P1 — Deep State Consistency Audit

Audit consistency between:

```text
Visit.status
VisitWorkflowStep.status
QueueTicket.status
QueueAcquisition.status
```

Look for invalid combinations such as:

```text
Visit = WAITING
Ticket = CANCELLED
```

or:

```text
Visit = CANCELLED
Ticket = CREATED
```

unless explicitly defined by business rules.

### P1 — Transfer/Workflow Consistency

Verify that transferring a ticket does not accidentally make runtime WorkflowStep state inconsistent.

### P1 — Add Integration Tests

Prioritize:
- kiosk acquisition
- kiosk registration
- kiosk cancellation
- online registration
- online check-in
- duplicate check-in
- workflow progression
- hold/resume
- cancel/no-show/skip
- transfer
- referral concurrency
- priority propagation

### P2 — API Contract Audit

Review:
- API Resources
- HTTP status codes
- error response format
- validation responses
- pagination
- `/api/v1/user`

---

## Core Mental Model

```text
                       ┌──────────────┐
                       │   Patient    │
                       └──────┬───────┘
                              │
                              ▼
                         ┌─────────┐
                         │  Visit  │
                         └────┬────┘
                              │
                              ▼
                    ┌──────────────────┐
                    │ WorkflowVersion  │
                    └────────┬─────────┘
                             │
                             ▼
                       WorkflowStep
                             │
                             ▼
                  VisitWorkflowStep
                             │
                             ▼
                       QueueTicket
                             │
                             ▼
                          Station
```

Service hierarchy:

```text
API Controller
      │
      ├── FormRequest / Policy
      │
      ▼
Domain Service
      │
      ├── QueueService
      │      └── WorkflowEngine
      │
      ├── CreateVisit
      │      └── WorkflowEngine
      │
      ├── QueueAcquisitionService
      │      └── WorkflowEngine
      │
      └── ReferralService
             └── CreateVisit
```

Core architectural principle:

> Visit owns the clinical interaction.
> Workflow owns progression.
> QueueTicket owns a specific queue execution.
> Station owns where that execution happens.

---

## Immediate Next Task

After PR #5 CI completes, the next task should be:

**KIOSK CANCELLATION LIFECYCLE AUDIT**

Inspect and implement:

```text
QueueAcquisitionService::cancel()
        ↓
QueueTicket cancellation
        ↓
VisitWorkflowStep cancellation
        ↓
Visit cancellation
        ↓
QueueAcquisition cancellation
        ↓
QueueEvent / AuditLog
```

Everything should happen atomically.

Add feature tests proving that cancelling a kiosk acquisition leaves no active/orphan queue.

Do this before frontend work.
