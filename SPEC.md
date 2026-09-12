# AGENTS.md

# Medical Facility Queue System — Backend Architecture & Agent Instructions

## 1. Project Goal

This project is a backend/API-first medical facility queue management system.

The system must support:

- Online patient registration
- Offline/Kiosk queue registration
- Reception/walk-in registration
- Multiple service/poli
- Multiple stations/computers per poli
- Nurse and doctor stations
- Multi-step patient workflows
- Queue tickets for individual workflow steps
- Automatic workflow progression
- Inter-poli referral
- Queue priority management
- Transfer between stations
- Authentication and authorization
- Audit/event tracking
- Concurrent queue processing

The current development priority is:

> BACKEND AND API ONLY.

Do NOT implement the React frontend unless explicitly requested.

---

# 2. Technology

The application is a fresh Laravel application initialized with:

- Laravel
- Laravel React Starter Kit
- PHP
- Eloquent ORM
- Laravel API
- Laravel Sanctum where authentication is required
- Pest/PHPUnit for testing

Use Laravel conventions unless there is a strong architectural reason not to.

Prefer:

- Eloquent
- Form Requests
- Policies
- API Resources
- Domain services
- Enums
- Database transactions
- Explicit domain logic

Avoid unnecessary abstractions.

Do not introduce a framework or package unless it is actually required.

---

# 3. Core Domain Principle

The most important architectural rule is:

> POLI/SERVICE IS NOT THE SAME AS STATION/COMPUTER.

A poli represents the service the patient wants.

A station represents the physical location/computer/staff workstation that performs one workflow step.

Example:

    Poli Penyakit Dalam
        |
        +-- Nurse Station
        |
        +-- Doctor Station

Another example:

    Poli Jantung
        |
        +-- Nurse Station
        |
        +-- Doctor Station

Do NOT model the computer itself as a poli.

---

# 4. Core Domain Model

The conceptual architecture is:

    Patient
        |
        v
      Visit
        |
        +--------------------+
        |                    |
        v                    v
    Service/Poli          Referral
        |                    |
        v                    v
     Workflow          Target Service/Poli
        |
        v
   WorkflowStep
        |
        v
 VisitWorkflowStep
        |
        v
   QueueTicket
        |
        v
     Station

Important:

- Patient represents the person.
- Visit represents one healthcare visit/session.
- Service/Poli represents the desired medical service.
- Workflow represents the ordered process for a service.
- WorkflowStep represents one stage of that workflow.
- VisitWorkflowStep represents the runtime execution of a workflow step for one visit.
- QueueTicket represents the queue position for one workflow step.
- Station represents the physical workstation/service point.
- Referral represents a clinical/business decision to send a patient to another service/poli.

---

# 5. Intake Channels

There are multiple ways a patient can enter the system:

1. Online
2. Kiosk
3. Reception / staff

These are intake channels, NOT separate queue systems.

All channels must converge into the same Visit/Workflow/Queue architecture.

Conceptually:

    ONLINE
       |
    KIOSK
       |
    RECEPTION
       |
       v
     Visit
       |
       v
    Workflow
       |
       v
 QueueTicket


Never create separate queue engines for:

- OnlineQueue
- KioskQueue
- WalkInQueue

There is ONE queue domain.

The source/channel may be stored as metadata on the visit or intake record.

---

# 6. Kiosk Flow

The real-world offline flow is:

    Patient arrives
        |
        v
      Kiosk
        |
        v
   Select Poli/Service
        |
        v
      Create Visit
        |
        v
  Resolve Workflow
        |
        v
 First WorkflowStep
        |
        v
Registration Queue
        |
        v
      A-024
        |
        v
Registration Desk

The kiosk does NOT need to perform full administrative registration.

The kiosk primarily captures:

- desired service/poli
- minimum information required by the business rules
- creates the visit/intake
- creates the first queue ticket

The patient then proceeds to the registration desk.

---

# 7. Online Flow

Online patients can obtain their queue before physically arriving.

Example:

    Patient
       |
       v
   Online App
       |
       v
   Select Poli
       |
       v
   Online Registration
       |
       v
      Visit
       |
       v
    Workflow
       |
       v
Registration WorkflowStep
       |
       v
 QueueTicket
       |
       v
      A-025
       |
       v
AWAITING_CHECKIN
       |
       v
Patient arrives
       |
       v
Physical Check-in
       |
       v
Waiting for Registration
       |
       v
Registration Desk

IMPORTANT:

Online registration should create the queue ticket before physical arrival.

Physical check-in must NOT create a second registration queue ticket if one already exists.

---

# 8. Reception / Walk-in Flow

Reception can create a visit for a patient directly.

Example:

    Reception
       |
       v
Find/Create Patient
       |
       v
Create Visit
       |
       v
Resolve Workflow
       |
       v
Registration Step
       |
       v
QueueTicket
       |
       v
A-026

Depending on business rules, the walk-in flow may immediately enter the waiting state.

The important principle is:

> Online, kiosk, and reception eventually use the same workflow and queue engine.

---

# 9. Poli and Station Architecture

A poli can have multiple stations.

Example:

    POLI UMUM

    Station 1:
        Nurse Computer

    Station 2:
        Doctor Computer

Therefore:

    Service/Poli
        |
        +-- Workflow Step: Nurse
        |       |
        |       +-- Nurse Station
        |
        +-- Workflow Step: Doctor
                |
                +-- Doctor Station

A station can represent:

- nurse computer
- doctor computer
- registration counter
- laboratory workstation
- radiology workstation
- pharmacy counter
- billing counter
- etc.

Station is where queue work happens.

---

# 10. Example Workflow

Example:

    Poli Penyakit Dalam

    Workflow:

    Step 1
        Registration
        Station: Registration Desk
        requires_queue = true

    Step 2
        Nurse / Triage
        Station: Nurse Station
        requires_queue = true

    Step 3
        Doctor Consultation
        Station: Doctor Station
        requires_queue = true

After registration:

    A-024 COMPLETED
        |
        v
    WorkflowEngine
        |
        v
    Nurse Step
        |
        v
    T-018 CREATED

After nurse:

    T-018 COMPLETED
        |
        v
    WorkflowEngine
        |
        v
    Doctor Step
        |
        v
    C-011 CREATED

After doctor:

    C-011 COMPLETED
        |
        v
    WorkflowEngine
        |
        +---- workflow finished
        |
        +---- referral
        |
        +---- next workflow action

---

# 11. Queue Ticket Principle

A QueueTicket belongs to a specific workflow step execution.

It does NOT represent the entire patient visit.

Example:

    Visit V001

        A-024
        Registration

        T-018
        Nurse

        C-011
        Doctor

All three tickets belong to:

    Visit V001

Therefore:

> One Visit can have many QueueTickets.

This is fundamental.

---

# 12. Queue Number Principle

Queue numbers identify queue tickets, not patients.

Example:

    Visit V001

        A-024
        T-018
        C-011

These are three different queue tickets.

A queue number does not need to remain the same throughout the visit.

Different stations may use different prefixes.

Example:

    Registration = A
    Triage/Nurse = T
    Consultation = C
    Laboratory = L
    Radiology = R
    Pharmacy = P
    Billing = B

Example:

    A-024
       |
       v
    T-018
       |
       v
    C-011

---

# 13. Priority Principle

Queue number and queue order are different concepts.

Example:

    C-100 NORMAL
    C-101 NORMAL
    C-102 PRIORITY
    C-103 NORMAL

Priority determines who is selected next.

Queue number does NOT determine service order.

The queue selector should conceptually use:

    priority DESC
    internal_sequence ASC

This provides:

- higher priority first
- FIFO within the same priority

Priority must be controlled by trusted backend rules.

Public users must not be able to arbitrarily claim emergency priority.

---

# 14. Queue Selector

QueueSelector must only select eligible queue tickets.

ON_HOLD tickets must NOT automatically return through callNext().

Example:

    CREATED
        |
        v
    QueueSelector
        |
        v
    CALL NEXT

ON_HOLD:

    ON_HOLD
        |
        X
    QueueSelector

Instead:

    ON_HOLD
        |
        v
    Explicit Resume
        |
        v
    CALLED

Do not implement:

    callNext()
        |
        +-- automatically resume ON_HOLD

---

# 15. Queue State Machine

QueueTicket states should be explicitly controlled.

Typical flow:

    CREATED
       |
       v
    CALLED
       |
       v
    IN_PROGRESS
       |
       v
    COMPLETED

Alternative states may include:

    CANCELLED
    SKIPPED
    NO_SHOW
    ON_HOLD
    TRANSFERRED

ON_HOLD:

    IN_PROGRESS
        |
        v
     ON_HOLD
        |
        v
     RESUME
        |
        v
      CALLED

Do not allow arbitrary state mutation.

All state changes must go through domain logic.

---

# 16. Transfer vs Referral

THIS IS A CRITICAL DISTINCTION.

## Transfer

Transfer means:

> The patient remains in the same workflow step but is moved to another station.

Example:

    Doctor Station 1
          |
          v
    Doctor Station 2

The workflow step remains the same.

Transfer must NOT create a new workflow step.

It may create a replacement/new queue ticket according to the queue design, but the workflow context remains the same.

---

## Referral

Referral means:

> The patient is sent to another medical service/poli.

Example:

    Poli Penyakit Dalam
          |
          v
    Poli Jantung

Referral is NOT the same as transfer.

Referral may create a new workflow for the target service.

Example:

    Visit V001

    Poli Penyakit Dalam
        |
        +-- Registration
        +-- Nurse
        +-- Doctor
                    |
                    v
                 Referral
                    |
                    v
              Poli Jantung
                    |
                    +-- Nurse
                    +-- Doctor

This distinction must be preserved in the domain model.

Do NOT implement inter-poli referral using a generic station transfer.

---

# 17. Referral Into an Existing Queue

A target poli may already have patients waiting.

Example:

    Poli Jantung

    C-020
    C-021
    C-022
    C-023

A doctor refers another patient.

The system creates:

    C-024

The existing queue is NOT reset or modified.

The new ticket joins the target queue according to its priority.

If NORMAL:

    C-020
    C-021
    C-022
    C-023
    C-024

If PRIORITY:

The QueueSelector may select the new ticket before NORMAL tickets.

Therefore:

> Ticket number does not guarantee queue order.

---

# 18. Referral Workflow

Referral should conceptually work like:

    Current Doctor
        |
        v
    Create Referral
        |
        +-- source visit
        +-- source service
        +-- target service
        +-- reason
        +-- priority if permitted
        |
        v
    Resolve Target Workflow
        |
        v
    Create Target Workflow Runtime
        |
        v
    First Target WorkflowStep
        |
        v
    Create QueueTicket
        |
        v
    Target Poli Queue

Do not simply change:

    current_station_id -> target_station_id

That would incorrectly model referral as transfer.

---

# 19. WorkflowEngine

WorkflowEngine is responsible for runtime workflow progression.

It should be the central domain service for:

1. Determining the current workflow step
2. Determining whether the current step is complete
3. Resolving the next applicable workflow step
4. Respecting step ordering
5. Handling optional steps
6. Handling repeatable steps
7. Respecting completion requirements
8. Creating VisitWorkflowStep records
9. Creating QueueTickets when required
10. Resolving the correct station
11. Completing the Visit when the workflow is complete
12. Starting target workflow progression when referral occurs

QueueService should NOT contain duplicated workflow progression logic.

---

# 20. QueueService Responsibility

QueueService is responsible for queue operations.

Examples:

    callNext()
    startTicket()
    completeTicket()
    skipTicket()
    cancelTicket()
    holdTicket()
    resumeTicket()
    transferTicket()

QueueService should enforce:

- state transitions
- queue selection
- concurrency safety
- queue events
- audit events
- station constraints

When completion affects workflow progression:

    QueueService
        |
        v
    WorkflowEngine

Do not duplicate workflow progression inside QueueService.

---

# 21. Visit Status

Visit status must describe the overall visit lifecycle.

Conceptual flow:

Online:

    REGISTERED
        |
        v
    AWAITING_CHECKIN
        |
        v
    CHECKED_IN
        |
        v
    WAITING
        |
        v
    IN_PROGRESS
        |
        v
    COMPLETED

Walk-in:

    REGISTERED
        |
        v
    CHECKED_IN
        |
        v
    WAITING

The exact persistence semantics must follow the project specification.

Do not silently skip a state merely because another state seems more convenient.

---

# 22. Authorization

Authentication is NOT authorization.

Every protected API must enforce:

- user authentication
- role/permission
- department scope where applicable
- station scope where applicable
- ownership/context where applicable

Policies should fail closed.

Never use:

    return true;

as a fallback for sensitive authorization.

Example:

    Nurse from Poli A
        |
        X
    cannot operate arbitrary Poli B station

Doctor should not automatically gain access to:

- pharmacy
- billing
- unrelated departments

unless explicitly authorized.

---

# 23. Public API Security

Public online endpoints must never trust client-controlled sensitive fields.

Examples:

Do not allow public clients to submit arbitrary:

- patient_id
- priority
- internal station_id
- internal workflow_id
- assigned user
- department
- authorization-related fields

Sensitive values must be resolved server-side.

Prevent IDOR vulnerabilities.

A patient should only be able to access their own public visit/status context according to the defined public-access mechanism.

---

# 24. API Design

API should be modular and versioned.

Use:

    /api/v1/...

Group endpoints conceptually:

    /api/v1/online/...
    /api/v1/reception/...
    /api/v1/queue/...
    /api/v1/referrals/...
    /api/v1/workflows/...

Controllers should remain thin.

Business logic belongs in:

- Domain services
- Policies
- Models
- Form Requests
- dedicated domain components

Do not place large business workflows inside controllers.

---

# 25. API Response Format

Use a consistent response structure.

Success:

    {
        "success": true,
        "message": "...",
        "data": {}
    }

Error:

    {
        "success": false,
        "message": "...",
        "errors": {}
    }

Use API Resources for stable response contracts where appropriate.

---

# 26. Database Design Principles

Database must represent the domain rather than frontend convenience.

Important relationships:

    patients
        |
        +-- visits
                |
                +-- workflow context
                |
                +-- visit_workflow_steps
                        |
                        +-- queue_tickets
                                |
                                +-- stations

Services/poli should be represented separately from stations.

Workflows should be reusable definitions.

Visit runtime should reference the workflow/version that was active for that visit.

---

# 27. Workflow Versioning

Workflow definitions may change over time.

A patient visit must not unexpectedly change behavior because an administrator edits the workflow after the visit started.

Therefore the system should support workflow versioning.

Example:

    Poli Umum Workflow v1
    Poli Umum Workflow v2

A Visit must retain the workflow version/context used for that visit.

Do not mutate an active visit's workflow implicitly.

Before changing schema related to workflow versioning:

1. Inspect the current schema.
2. Inspect existing relationships.
3. Determine whether version belongs to definition, runtime, or both.
4. Write migrations deliberately.
5. Add regression tests.

Never invent schema relationships just to satisfy a test.

---

# 28. Concurrency

Queue operations are concurrent by nature.

Two staff members may call next at the same time.

The backend must prevent the same ticket from being assigned twice.

Use appropriate database transactions and row-level locking.

For example:

    transaction
        |
        v
    select eligible ticket
        |
        v
    lock row
        |
        v
    transition ticket
        |
        v
    commit

Concurrency behavior must be tested explicitly.

---

# 29. Events and Audit

Important queue operations should generate domain/audit records.

Examples:

    created
    called
    started
    completed
    skipped
    cancelled
    no_show
    on_hold
    resumed
    transferred

Referral events should also be auditable.

Audit information should allow the system to answer:

- who performed the action?
- when?
- on which ticket?
- on which visit?
- at which station?
- what changed?
- why, when applicable?

---

# 30. Testing Architecture

Testing must remain modular.

## Unit Tests

Unit tests test isolated domain logic.

Examples:

    tests/Unit/Domain/Queue/QueueStateMachineTest.php
    tests/Unit/Domain/Queue/QueueNumberGeneratorTest.php
    tests/Unit/Domain/Queue/QueueSelectorTest.php
    tests/Unit/Domain/Workflow/WorkflowEngineTest.php

Unit tests should NOT depend on HTTP.

Do not use RefreshDatabase universally in Unit tests.

Mock dependencies when necessary.

---

## Feature Tests

Feature tests test HTTP/API behavior.

Each file should have ONE clear responsibility.

Good:

    RegisterTest.php
    CheckInTest.php
    StatusTest.php
    CallNextTest.php
    CompleteTicketTest.php
    TransferTicketTest.php
    ReferralTest.php

Bad:

    QueueEverythingTest.php

Do not put unrelated API features into one test file.

---

## Integration Tests

Complex cross-domain scenarios should be tested separately.

Examples:

    OnlineFullJourneyTest.php
    WalkInFullJourneyTest.php
    ReferralJourneyTest.php
    TransferWorkflowTest.php
    ConcurrencyCallNextTest.php
    PriorityOrderingTest.php

Integration tests may use the database.

---

# 31. Required Core Test Scenarios

The backend must eventually test:

### Online

    Online registration
        |
        v
    Visit created
        |
        v
    Queue ticket created
        |
        v
    AWAITING_CHECKIN
        |
        v
    Physical check-in
        |
        v
    Registration queue

---

### Kiosk

    Kiosk
        |
        v
    Select Poli
        |
        v
    Visit
        |
        v
    Registration Queue
        |
        v
    Ticket printed

---

### Walk-in

    Reception
        |
        v
    Patient
        |
        v
    Visit
        |
        v
    Registration Queue

---

### Multi-step Poli

    Registration
        |
        v
    Nurse
        |
        v
    Doctor
        |
        v
    Completed

---

### Referral

    Doctor Poli A
        |
        v
    Referral
        |
        v
    Poli B
        |
        v
    Existing queue remains
        |
        v
    New ticket joins queue

---

### Transfer

    Station A
        |
        v
    Transfer
        |
        v
    Station B

Workflow step remains the same.

---

### Priority

    Emergency/Priority
        >
    Appointment
        >
    Normal

Exact priority rules must follow the application specification.

---

### Concurrency

Two users calling next simultaneously must never receive the same queue ticket.

---

# 32. API Completeness Rule

Before declaring the backend complete, verify the API against the application requirements.

Do not only verify that existing endpoints return 200.

For every feature ask:

1. Does the endpoint exist?
2. Is validation correct?
3. Is authorization correct?
4. Does it use the correct domain service?
5. Does it enforce state transitions?
6. Does it update the correct database records?
7. Does it emit events/audit logs?
8. Does it handle concurrency?
9. Is the response contract correct?
10. Is there a dedicated feature test?
11. Is there an integration test if the feature crosses domains?

---

# 33. Definition of Backend Ready

Do NOT declare the backend ready merely because:

    php artisan test

passes.

Backend readiness requires:

- Database migrations work from fresh database
- Seeders work
- Core domain services work
- Queue state machine works
- WorkflowEngine works
- Online flow works
- Kiosk flow works
- Reception flow works
- Multi-step workflow works
- Referral works
- Transfer works
- Priority works
- Concurrency is protected
- Authorization is enforced
- Public APIs are secure
- API resources/contracts are stable
- Feature tests are modular
- Unit tests are isolated
- Integration tests cover critical journeys
- Audit/event tracking works

All critical requirements must be traceable to the specification.

---

# 34. Agent Working Rules

Before modifying code:

1. Read this AGENTS.md.
2. Read SPEC.md if it exists.
3. Inspect the current schema.
4. Inspect related models.
5. Inspect existing domain services.
6. Inspect related tests.
7. Understand existing relationships before creating new ones.

Do not blindly implement assumptions.

When requirements are ambiguous:

- STOP before making architectural changes.
- Explain the ambiguity.
- Give the possible options.
- Recommend one based on the existing architecture.
- Ask for confirmation if the decision affects database/domain architecture.

---

# 35. Do Not Overengineer

Do not create abstractions simply because they sound architecturally sophisticated.

For example:

Do not create:

    QueueManagerFactoryResolverStrategyProvider

unless there is a real requirement.

Prefer:

    QueueService
    WorkflowEngine
    ReferralService
    CreateVisit

with clear responsibilities.

---

# 36. Domain Boundary Rules

QueueService:

    queue lifecycle

WorkflowEngine:

    workflow progression

CreateVisit:

    visit creation/intake convergence

CheckInVisit:

    physical check-in

ReferralService:

    inter-poli referral

Authorization/Policies:

    access control

Controllers:

    HTTP orchestration only

FormRequests:

    request validation

API Resources:

    response representation

Models:

    persistence and relationships

Keep these responsibilities separated.

---

# 37. Referral Is a First-Class Concept

Referral must eventually have explicit domain representation.

At minimum, the design should be capable of representing:

- source visit
- source service/poli
- target service/poli
- referring user
- reason
- priority where allowed
- status
- timestamps
- resulting target workflow/visit context
- resulting queue ticket where applicable

Do NOT hide referral entirely inside QueueTicket.transfer.

---

# 38. Kiosk Is an Intake Channel

Kiosk should not have a completely separate queue architecture.

Conceptually:

    Kiosk
      |
      v
    CreateVisit
      |
      v
    WorkflowEngine
      |
      v
    QueueService

The future physical kiosk hardware may print:

    A-024

but printing is an external/presentation concern.

The backend's responsibility is to create and return the queue ticket.

---

# 39. Backend First

Until explicitly requested:

DO NOT:

- build React pages
- build dashboard UI
- build kiosk UI
- build printing UI
- optimize frontend UX
- add WebSocket UI
- add frontend state management

Focus on:

    Database
        +
    Domain
        +
    API
        +
    Authorization
        +
    Tests

---

# 40. Development Order

Recommended backend implementation order:

## Phase A — Foundation

- database schema
- enums
- models
- relationships
- factories
- seeders

## Phase B — Intake

- online registration
- kiosk registration
- reception/walk-in registration
- patient lookup/create
- physical check-in

## Phase C — Queue Core

- queue number generation
- queue selector
- queue state machine
- call next
- start
- complete
- skip
- hold
- resume
- cancel
- transfer

## Phase D — Workflow Engine

- workflow definition
- workflow steps
- visit workflow runtime
- automatic next-step progression
- automatic queue creation
- workflow completion

## Phase E — Referral

- referral model
- referral service
- source/target poli
- target workflow resolution
- target queue creation
- referral authorization
- referral tests

## Phase F — Authorization

- roles
- policies
- department scope
- station scope
- public access rules
- IDOR protection

## Phase G — API Hardening

- API Resources
- response consistency
- API versioning
- validation
- error handling
- audit events

## Phase H — Verification

- Unit tests
- Feature tests
- Integration tests
- concurrency tests
- fresh migration test
- seeder test
- API completeness audit

---

# 41. Current Development Scope

The current project should focus on:

> CORE BACKEND + DATABASE + API.

Frontend is explicitly out of scope until backend readiness is verified.

Do not prematurely implement:

- WebSockets
- notifications
- advanced analytics
- kiosk UI
- printing hardware integration
- React dashboard

unless explicitly requested.

---

# 42. Final Architecture

The intended high-level architecture is:

                         INTAKE CHANNELS

                +----------+----------+
                |          |          |
              ONLINE     KIOSK     RECEPTION
                |          |          |
                +----------+----------+
                           |
                           v
                         VISIT
                           |
                           v
                     SERVICE / POLI
                           |
                           v
                       WORKFLOW
                           |
                           v
                    WORKFLOW STEP
                           |
                           v
                  VISIT WORKFLOW STEP
                           |
                           v
                     QUEUE TICKET
                           |
                           v
                        STATION
                           |
                           v
                    STAFF / COMPUTER


                    REFERRAL
                        |
                        v
                 TARGET SERVICE
                        |
                        v
                 TARGET WORKFLOW
                        |
                        v
                TARGET QUEUE TICKET


Important distinction:

    POLI      = medical service
    STATION   = physical workstation
    WORKFLOW  = ordered service process
    STEP      = one stage of that process
    TICKET    = queue position for one stage
    TRANSFER  = move within same workflow step
    REFERRAL  = move/continue into another medical service
    VISIT     = overall patient encounter

This distinction must remain consistent throughout the database,
domain services, APIs, and tests.