You are now responsible for implementing the KIYU frontend.

IMPORTANT:
The backend/API implementation and the frontend API contract documentation already exist in this repository. Treat the existing backend and API contract documentation as the source of truth.

Do NOT redesign the backend.
Do NOT modify backend business rules just to make the frontend easier.
Do NOT invent API endpoints, request fields, response structures, enums, permissions, or workflows that are not documented or implemented.

The goal is to build the frontend on top of the existing backend.

==================================================
OBJECTIVE
==================================================

Create a new git branch:

    frontend

Then implement the KIYU frontend using:

- Laravel
- Inertia.js
- React
- TypeScript
- shadcn/ui for the dashboard UI
- Tailwind CSS
- existing authentication/API contract
- existing backend domain and workflow rules

The frontend must consume the existing API/backend contract instead of duplicating business logic.

The frontend should be production-oriented, maintainable, responsive, and structured for future expansion.

==================================================
PHASE 0 — INSPECT BEFORE CODING
==================================================

Before making changes:

1. Create/check out branch:

    frontend

2. Inspect the repository structure.

3. Read and understand:

    - SPEC.md
    - FRONTEND_API_CONTRACT.md
    - existing frontend configuration
    - package.json
    - vite configuration
    - Laravel configuration
    - existing Inertia configuration, if any
    - existing React configuration, if any
    - Tailwind configuration
    - existing API Resources/contracts
    - existing API documentation
    - routes
    - authentication implementation
    - authorization rules
    - workflow/queue domain terminology

4. Find the existing frontend API contract documentation.

This documentation is the primary source of truth for:
    - endpoint names
    - HTTP methods
    - request payloads
    - response structures
    - validation errors
    - authentication
    - authorization
    - enums
    - status values
    - pagination
    - error responses

5. Compare the API documentation against the current backend implementation.

Do not silently "fix" contract mismatches.

Create a short implementation note containing:

    BACKEND CONTRACT STATUS
    - available endpoints
    - frontend-relevant resources
    - authentication mechanism
    - current frontend gaps
    - contract mismatches, if any

If the API documentation and backend disagree, stop and clearly identify the mismatch before implementing the affected UI.

==================================================
FRONTEND ARCHITECTURE
==================================================

Use Inertia + React as the primary application UI.

Do not create a separate frontend SPA outside Laravel unless the existing repository already requires it.

Prefer:

    Laravel
        ↓
    Inertia
        ↓
    React + TypeScript
        ↓
    shadcn/ui

Use server-side routing through Laravel/Inertia where appropriate.

Keep API/network communication isolated in a clear layer.

Recommended structure, adapting to the existing repository where necessary:

    resources/js/
        components/
        components/ui/
        layouts/
        lib/
        hooks/
        pages/
        types/
        api/
        app.tsx

Do not create unnecessary abstractions.

Use reusable components when there is genuine reuse.

Avoid a giant global component system.

==================================================
SHADCN/UI
==================================================

Use shadcn/ui for the dashboard interface.

Use shadcn components where appropriate, such as:

- Button
- Input
- Label
- Card
- Table
- Badge
- Dialog
- DropdownMenu
- Sheet
- Select
- Tabs
- Alert
- Skeleton
- Toast/Sonner
- Pagination
- Tooltip
- Separator
- Avatar
- Command

Do not manually recreate shadcn components unnecessarily.

Maintain a clean and consistent design system.

Use responsive layouts.

Desktop dashboard is important, but the interface must remain usable on smaller screens.

==================================================
DESIGN DIRECTION
==================================================

The application is a medical facility queue management system.

The UI should feel:

- professional
- clean
- calm
- operational
- readable
- efficient
- not overly decorative

Prioritize information density and operational clarity.

Avoid excessive animations.

Queue-related information must be immediately understandable.

Statuses should have consistent visual treatment.

Examples:

    CREATED
    CALLED
    IN_PROGRESS
    ON_HOLD
    COMPLETED
    SKIPPED
    CANCELLED
    NO_SHOW
    TRANSFERRED

Do not invent additional status values.

Use the exact backend/API terminology.

==================================================
USER ROLES
==================================================

The frontend must respect the backend authorization model.

Potential user roles include:

- PATIENT
- RECEPTIONIST
- NURSE
- DOCTOR
- PHARMACY
- LAB
- STAFF
- ADMIN

Do not assume every role can access every page.

The frontend should hide irrelevant navigation and UI actions where possible.

However:

Frontend authorization is NOT a security boundary.

The backend remains authoritative.

Never rely on UI hiding as authorization.

==================================================
DASHBOARD
==================================================

Create the dashboard shell using shadcn/ui.

The dashboard should include:

- sidebar navigation
- top navigation/header
- user information
- role-aware navigation
- responsive mobile navigation
- page content area
- breadcrumbs where useful
- consistent page headers
- loading states
- empty states
- error states

The sidebar/navigation must be generated from the user's accessible functionality rather than showing every feature to everyone.

Do not hardcode access rules differently from the backend contract.

==================================================
QUEUE OPERATOR UI
==================================================

The queue operator interface is one of the most important parts of the frontend.

Implement a clear queue/station workflow around the existing backend APIs.

The UI should support the backend capabilities documented in the API contract, including where available:

- station selection
- call next
- view active ticket
- start ticket
- complete ticket
- hold ticket
- resume ticket
- skip ticket
- no-show
- cancel
- transfer

The UI must respect the actual backend state machine.

Do not allow invalid transitions from the frontend.

Examples:

CREATED
    → CALLED

CALLED
    → IN_PROGRESS
    → ON_HOLD
    → SKIPPED
    → CANCELLED
    → TRANSFERRED

IN_PROGRESS
    → COMPLETED
    → ON_HOLD
    → SKIPPED
    → CANCELLED
    → TRANSFERRED

ON_HOLD
    → CALLED
    → CANCELLED

Do not manually duplicate transition logic in multiple components.

Centralize presentation-level transition availability where practical, while treating backend responses as authoritative.

==================================================
QUEUE DISPLAY
==================================================

Implement a queue display page using the public queue API when supported by the contract.

The public queue display must not expose private patient information.

It should clearly show:

- current called ticket
- current service/status
- upcoming tickets
- queue number
- station/service information where available

Do not expose:

- patient name
- medical record number
- national ID
- phone
- other sensitive information

unless explicitly provided and authorized by the API contract.

==================================================
PATIENT EXPERIENCE
==================================================

Implement the patient-facing frontend according to the documented APIs.

Potential pages/features include:

- login
- registration
- profile/me
- visits
- visit detail
- queue status
- online visit registration
- check-in
- referral information where exposed by API
- logout

Follow the API contract exactly.

Do not invent fields.

For example, do not assume that patient can:

- manually choose priority
- modify queue state
- cancel arbitrary queue tickets
- access another patient's visit

unless the backend explicitly allows it.

==================================================
ONLINE VISIT
==================================================

Implement the frontend for the online visit flow based on the documented API contract.

The expected conceptual flow is:

    Patient
      ↓
    Create online visit
      ↓
    Visit created
      ↓
    Awaiting check-in
      ↓
    Check-in
      ↓
    Waiting / queue
      ↓
    Workflow progression

Do not create duplicate queue-management logic in React.

The backend WorkflowEngine remains responsible for workflow progression.

The frontend only presents and triggers the documented actions.

==================================================
RECEPTION
==================================================

Implement reception screens according to the API contract.

Potential functionality:

- patient search
- patient selection
- create visit
- kiosk acquisition registration
- patient registration against an existing kiosk acquisition

The interface should make it easy for reception staff to process patients quickly.

Use:

- searchable tables
- filters
- dialogs
- confirmation dialogs
- clear status badges
- form validation
- server validation error presentation

Do not use mock data in production-facing screens unless explicitly marked as temporary development fixtures.

==================================================
KIOSK
==================================================

Implement kiosk UI according to the existing kiosk API contract.

The kiosk flow should be simple and fast:

    Select department/service
          ↓
    Acquire queue
          ↓
    Show queue number / result

Do not ask for patient registration information during the initial queue acquisition if the backend contract models kiosk intake as queue acquisition first.

Do not create a fake TemporaryPatient concept in the frontend.

Use the actual QueueAcquisition API.

If idempotency is required by the API contract, implement it exactly as documented.

==================================================
REFERRAL
==================================================

Follow the existing referral API contract.

Do not invent a referral acceptance workflow.

IMPORTANT:
The current architectural decision is:

    Creating a referral should create/start the target visit/workflow immediately,
    unless the existing backend contract explicitly says otherwise.

Therefore the frontend should be prepared to consume:

    Referral
    Target Visit
    Target Workflow / Queue information

when returned by the API.

Do not introduce:

    POST /referrals/{id}/accept

unless that endpoint actually exists in the backend contract.

==================================================
API CLIENT / DATA ACCESS
==================================================

Create a clean API/data access layer.

Do not scatter raw fetch/axios calls across every React component.

Use a consistent pattern.

For example:

    api/
        auth.ts
        visits.ts
        queue.ts
        referrals.ts
        patients.ts
        kiosk.ts

Adapt this structure to the existing project.

The API layer should handle:

- HTTP methods
- authentication
- JSON headers
- CSRF requirements where applicable
- response parsing
- API errors
- validation errors
- unauthorized responses
- forbidden responses
- not found
- server errors

Do not duplicate API URL strings throughout components.

Use TypeScript types matching the documented API responses.

Do not use `any` as a shortcut.

==================================================
TYPE SAFETY
==================================================

Create proper TypeScript types for:

- User
- Patient
- Visit
- Workflow
- WorkflowStep
- VisitWorkflow
- VisitWorkflowStep
- QueueTicket
- Station
- Department
- QueueAcquisition
- Referral
- API error responses
- pagination structures
- relevant enums/statuses

However:

Do NOT blindly recreate backend PHP models one-to-one.

Create frontend types according to the actual API contract.

Only expose fields that the API actually returns.

==================================================
FORMS
==================================================

Use an appropriate React form approach compatible with the existing project.

Forms must support:

- client-side basic validation
- server-side validation errors
- disabled submitting state
- loading state
- success state
- API error state

Server validation remains authoritative.

Do not duplicate complex backend validation rules unnecessarily.

==================================================
LOADING / ERROR / EMPTY STATES
==================================================

Every data-driven page must have explicit states:

1. loading
2. success
3. empty
4. validation error
5. authorization error
6. server/API error

Do not leave blank screens during loading.

Use Skeleton components where appropriate.

Use readable empty states.

==================================================
MUTATIONS
==================================================

For actions such as:

- call next
- start
- complete
- hold
- resume
- skip
- no-show
- cancel
- transfer

the UI should:

1. disable the action while the request is running;
2. prevent accidental double-submission;
3. display the API result;
4. refresh/update relevant data;
5. display validation/business-rule errors;
6. recover correctly after errors.

Do not optimistically change state when doing so could cause frontend/backend inconsistency unless the API contract and UX justify it.

For queue state changes, prefer confirmed server state.

==================================================
CONCURRENCY
==================================================

The queue system is concurrency-sensitive.

Assume another operator may change a ticket simultaneously.

Therefore:

- do not assume the frontend's current state is authoritative;
- handle 409/business-rule responses appropriately;
- refresh queue/ticket state after failed mutations where appropriate;
- show a clear message when another operator already changed the ticket;
- never retry dangerous queue mutations blindly.

The backend QueueStateMachine remains authoritative.

==================================================
AUTHENTICATION
==================================================

Use the application's existing authentication mechanism.

Inspect the backend before implementing authentication state.

Do not invent a second auth mechanism.

Support:

- login
- registration where applicable
- authenticated session state
- current user
- logout if the backend provides it
- unauthorized handling
- redirect behavior

Never store sensitive credentials unnecessarily in localStorage.

Follow the existing Laravel/Inertia authentication architecture.

==================================================
ROUTING
==================================================

Create clean frontend routes/pages matching the application domain.

Suggested page organization:

    Pages/
        Auth/
        Dashboard/
        Patient/
        Reception/
        Queue/
        Kiosk/
        Referrals/
        Public/

Do not blindly use these names if the repository already has an established convention.

Keep URLs understandable.

Use backend routes/Inertia conventions consistently.

==================================================
DASHBOARD PAGE PRIORITY
==================================================

Implement in this order:

PHASE 1
    - application shell
    - authentication
    - dashboard layout
    - sidebar
    - header
    - role-aware navigation
    - user menu

PHASE 2
    - station/queue operator dashboard
    - queue list
    - active ticket
    - call next
    - start
    - complete
    - hold/resume
    - skip
    - no-show
    - cancel
    - transfer

PHASE 3
    - reception
    - patient search
    - visit creation
    - kiosk acquisition registration

PHASE 4
    - patient portal
    - visits
    - visit details
    - online registration
    - check-in
    - queue status

PHASE 5
    - kiosk UI
    - public queue display
    - referral screens

PHASE 6
    - UX polish
    - responsive improvements
    - accessibility
    - loading states
    - empty states
    - error handling
    - final cleanup

Do not attempt to build every page simultaneously.

==================================================
ACCESSIBILITY
==================================================

Use semantic HTML and accessible shadcn components.

Important areas:

- keyboard navigation
- visible focus states
- labels
- dialog accessibility
- button states
- form error announcements where practical
- sufficient contrast
- mobile usability

Do not rely only on color to indicate status.

==================================================
PERFORMANCE
==================================================

Avoid unnecessary API requests and React re-renders.

Do not create aggressive polling unless the backend/API contract requires real-time updates.

For queue dashboards, first inspect whether the existing contract provides polling-compatible endpoints.

If polling is needed, implement a controlled interval and clean it up properly.

Do not introduce websockets/realtime infrastructure unless the repository/backend already supports it or the task explicitly requires it.

==================================================
SECURITY
==================================================

The frontend must never:

- trust role information for authorization;
- expose sensitive patient data unnecessarily;
- allow arbitrary IDs to bypass backend authorization;
- construct unauthorized actions based on client-side assumptions;
- store secrets in frontend source code;
- hardcode API credentials.

Remember:

    frontend UX restrictions != backend authorization.

==================================================
TESTING
==================================================

After implementing each major feature:

- run the relevant frontend checks;
- run TypeScript checks;
- run lint;
- run Laravel tests when backend integration is involved;
- verify Inertia page rendering;
- verify navigation;
- verify API error handling.

Do not weaken existing backend tests.

Do not remove tests to make frontend implementation pass.

==================================================
NO MOCKED BACKEND CONTRACT
==================================================

Do not build a fake backend API just to make the UI work.

If temporary fixtures are needed for component development, isolate them clearly and remove them before completion.

The final UI should work against the existing application/backend.

==================================================
IMPORTANT BUSINESS RULES
==================================================

Respect these architecture principles:

1. QueueTicket represents execution of a workflow step, not the entire visit.

2. A Visit may have multiple QueueTickets.

3. A Station is a physical/service execution point, not a Department/Poli.

4. A Department/Poli may have multiple Stations.

5. WorkflowEngine controls workflow progression.

6. QueueStateMachine controls ticket state transitions.

7. Backend authorization is authoritative.

8. Queue priority is backend controlled.

9. Public users must not arbitrarily claim emergency priority.

10. Transfer and referral are different concepts.

11. Transfer means the same workflow step moves to another valid station.

12. Referral creates a target clinical workflow according to the current backend contract.

13. Queue number identifies a queue ticket, not a patient.

14. Do not assume queue order means service completion order.

15. Do not duplicate backend business logic in React.

==================================================
API CONTRACT RULE
==================================================

Before creating each page:

1. Locate the corresponding API contract.
2. Identify:
   - endpoint
   - request
   - response
   - authorization
   - validation errors
   - possible business errors
3. Implement against that contract.
4. Verify against the actual backend.
5. Only then build the UI around it.

If something is missing from the API contract:

DO NOT invent the endpoint.

Instead report:

    FRONTEND BLOCKER
    Endpoint/contract required:
    Why it is required:
    Current backend status:
    Suggested contract:

==================================================
CODE QUALITY
==================================================

Follow the existing repository conventions.

Prefer:

- small components
- clear naming
- reusable UI primitives
- typed API responses
- predictable state management
- minimal abstraction
- readable code

Avoid:

- giant components
- unnecessary global state
- duplicated API logic
- `any`
- deeply nested conditional rendering
- duplicated status logic
- hardcoded role checks everywhere
- excessive custom UI primitives when shadcn already provides them

==================================================
GIT
==================================================

Create and work on:

    frontend

Do not work directly on main.

Use focused commits.

Suggested commit sequence:

    feat: initialize inertia react frontend
    feat: add dashboard layout
    feat: add queue operator dashboard
    feat: add reception interface
    feat: add patient portal
    feat: add kiosk interface
    feat: add referral interface
    fix: refine frontend api integration
    chore: finalize frontend validation

Use appropriate commit messages based on actual changes.

Do not create meaningless commits.

==================================================
FINAL VERIFICATION
==================================================

Before declaring the frontend complete, verify:

[ ] branch `frontend` exists
[ ] application builds
[ ] TypeScript checks pass
[ ] lint passes
[ ] Inertia pages render
[ ] authentication works
[ ] role-aware navigation works
[ ] dashboard works
[ ] queue operator workflow works
[ ] reception workflow works
[ ] patient workflow works
[ ] kiosk workflow works
[ ] public queue display works
[ ] referral workflow works
[ ] loading states exist
[ ] empty states exist
[ ] validation errors are displayed
[ ] API/business errors are handled
[ ] unauthorized responses are handled
[ ] responsive layout works
[ ] no fake API contract remains
[ ] no backend business rule was incorrectly duplicated
[ ] no sensitive patient information is exposed publicly
[ ] existing backend tests still pass

==================================================
FINAL REPORT
==================================================

At the end, provide a concise implementation report:

1. Branch created
2. Frontend stack/configuration
3. Pages implemented
4. API endpoints consumed
5. Components/layouts created
6. Authentication implementation
7. Authorization-aware UI
8. Remaining TODOs
9. API/backend blockers
10. Tests/checks executed
11. Commands used
12. Commit list

For every incomplete item, explicitly classify it as:

    IMPLEMENTED
    PARTIAL
    BLOCKED
    NOT IMPLEMENTED

Do not claim something works unless you actually verified it.

Start by inspecting the repository and API contract documentation before writing frontend code.