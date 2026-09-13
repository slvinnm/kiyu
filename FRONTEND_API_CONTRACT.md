# Frontend API Contract

This document defines the API contract expected by the React/Inertia frontend layer for the KIYU backend.

## 1. Authentication

- Auth mechanism: Laravel Sanctum
- Header:
  - `Authorization: Bearer <token>`
- Patient auth endpoints are public:
  - `POST /api/v1/patient/register`
  - `POST /api/v1/patient/login`
- All other protected routes are under `auth:sanctum`.

### Common auth errors

- `401` unauthenticated
- `403` forbidden / wrong role or wrong station
- `422` invalid request state

---

## 2. Response format

Most endpoints return a JSON envelope like this:

```json
{
  "success": true,
  "message": "Queue ticket updated successfully.",
  "data": { }
}
```

On validation/state errors, the backend typically returns:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "department_code": [
      "The selected department code is invalid."
    ]
  }
}
```

Or for state logic:

```json
{
  "success": false,
  "message": "Queue ticket cannot be started from its current state.",
  "data": null
}
```

Frontend should treat these as:

- `success === true`: proceed normally
- `success === false`: show message, do not assume mutation success
- `errors`: attach field-level validation messages

---

## 3. Shared resource shapes

### Patient

```json
{
  "id": 1,
  "medical_record_number": "MR-001",
  "name": "John Doe",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "phone": "08123456789",
  "email": "john@example.com",
  "address": "Jl. Merdeka 1"
}
```

### Station

```json
{
  "id": 10,
  "code": "REG-01",
  "name": "Registration",
  "type": "registration",
  "queue_prefix": "A",
  "is_active": true,
  "department": {
    "id": 2,
    "code": "GENERAL",
    "name": "General"
  }
}
```

### Visit

```json
{
  "id": 5,
  "visit_number": "V-250901-0001",
  "status": "waiting",
  "priority": "normal",
  "intake_channel": "online",
  "department": {
    "id": 2,
    "code": "GENERAL",
    "name": "General"
  },
  "queue_tickets": [
    {
      "id": 20,
      "queue_number": "A-001",
      "status": "created",
      "priority": 1,
      "internal_sequence": 1,
      "station": {
        "id": 10,
        "code": "REG-01",
        "name": "Registration",
        "type": "registration"
      },
      "workflow_step": {
        "id": 3,
        "name": "Registration",
        "sequence": 1
      },
      "called_at": "2026-09-13T08:00:00.000Z",
      "started_at": null,
      "completed_at": null
    }
  ],
  "checked_in_at": null,
  "completed_at": null
}
```

### Queue Ticket

```json
{
  "id": 20,
  "queue_number": "A-001",
  "status": "created",
  "priority": 1,
  "internal_sequence": 1,
  "station": {
    "id": 10,
    "code": "REG-01",
    "name": "Registration",
    "type": "registration"
  },
  "visit": {
    "id": 5,
    "visit_number": "V-250901-0001",
    "status": "waiting"
  },
  "workflow_step": {
    "id": 3,
    "name": "Registration",
    "sequence": 1
  },
  "called_at": null,
  "started_at": null,
  "completed_at": null
}
```

---

## 4. Public endpoints

### GET /api/v1/public/queues/{station}

Used for queue display boards.

Response payload:

```json
{
  "success": true,
  "data": {
    "station": {
      "id": 10,
      "name": "Registration"
    },
    "tickets": [
      {
        "id": 20,
        "queue_number": "A-001",
        "status": "created"
      }
    ]
  }
}
```

---

## 5. Patient endpoints

### POST /api/v1/patient/register

Request:

```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "secret123",
  "phone": "08123456789",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "address": "Jl. Merdeka 1"
}
```

Response:

```json
{
  "success": true,
  "message": "Patient account registered successfully.",
  "data": {
    "token": "<sanctum-token>",
    "patient": { }
  }
}
```

### POST /api/v1/patient/login

Request:

```json
{
  "email": "john@example.com",
  "password": "secret123"
}
```

### GET /api/v1/patient/me

Protected patient-only route.

Response:

```json
{
  "success": true,
  "message": "Patient profile retrieved successfully.",
  "data": { }
}
```

### GET /api/v1/patient/visits

Returns all visits for the authenticated patient.

### GET /api/v1/patient/visits/{visit}

Returns one visit with nested `queue_tickets`.

### GET /api/v1/patient/queue

Returns active queue state for the patient.

### POST /api/v1/patient/visits/{visit}/check-in

Used when patient arrives and checks in.

---

## 6. Online endpoints

### POST /api/v1/online/visits

Protected patient-only route.

Request:

```json
{
  "department_code": "GENERAL"
}
```

Success response:

```json
{
  "success": true,
  "message": "Online visit registered successfully.",
  "data": { }
}
```

Frontend notes:

- Require authenticated patient user.
- Validate `department_code` must exist and be active.
- On success, redirect to visit detail or queue status page.

---

## 7. Kiosk endpoints

### GET /api/v1/kiosk/departments

Returns active departments available for kiosk intake.

### POST /api/v1/kiosk/queue-acquisitions

Request:

```json
{
  "department_code": "GENERAL"
}
```

Response includes an acquisition record and visit/ticket data.

Frontend notes:

- This is for walk-in kiosk flow before registration
- not the same as patient-facing online registration

---

## 8. Queue operations (staff)

### GET /api/v1/queue/stations

Returns stations visible to current staff user.

Rules:

- patient must never access this route
- non-admin staff only see their assigned station
- admin sees all active stations

### POST /api/v1/queue/stations/{station}/call-next

Call next ticket for a station.

Response:

```json
{
  "success": true,
  "message": "Next queue ticket called successfully.",
  "data": { }
}
```

Possible state error:

- if station already has `CALLED` or `IN_PROGRESS` ticket, return `422` or validation exception

### POST /api/v1/queue/tickets/{queueTicket}/start

### POST /api/v1/queue/tickets/{queueTicket}/complete

Request body:

```json
{
  "completion_context": {
    "repeat_current_step": false
  }
}
```

### POST /api/v1/queue/tickets/{queueTicket}/hold

### POST /api/v1/queue/tickets/{queueTicket}/resume

### POST /api/v1/queue/tickets/{queueTicket}/skip

Request:

```json
{
  "reason": "Patient requested to skip due to urgency.",
  "context": {}
}
```

### POST /api/v1/queue/tickets/{queueTicket}/no-show

### POST /api/v1/queue/tickets/{queueTicket}/cancel

### POST /api/v1/queue/tickets/{queueTicket}/transfer

Request:

```json
{
  "target_station_id": 123
}
```

Rules:

- target station must be active
- target station must belong to same department
- target station must be allowed for the current workflow step

---

## 9. Reception and referral endpoints

### POST /api/v1/reception/queue-acquisitions/{queueAcquisition}/register

Used by reception to attach real patient identity to kiosk acquisition.

### POST /api/v1/referrals/visits/{visit}

Create referral to another department.

### GET /api/v1/referrals/{referral}

Read referral details.

### PATCH /api/v1/priority/visits/{visit}

Update visit priority.

Request:

```json
{
  "priority": "emergency"
}
```

---

## 10. Frontend state handling recommendations

### Role-based UI

Frontend should branch by role:

- `patient`
  - online visit submission
  - patient profile
  - visit history
  - active queue
  - check in

- `receptionist`, `nurse`, `doctor`, `staff`, `pharmacy`, `lab`
  - queue stations
  - call next
  - start/complete/skip/hold/resume/cancel

- `admin`
  - all stations and admin-level access

### Queue state mapping

The frontend should handle these statuses exactly:

```ts
type QueueStatus =
  | 'created'
  | 'called'
  | 'in_progress'
  | 'completed'
  | 'cancelled'
  | 'skipped'
  | 'no_show'
  | 'on_hold'
  | 'transferred';
```

### Important UI rules

- `on_hold` must be resumed explicitly
- `callNext` should not be available on a station that already has a `called` or `in_progress` ticket
- `transfer` and `referral` are different flows and should not be merged in the UI
- `priority` is restricted; patient cannot arbitrarily choose emergency priority

---

## 11. Recommended frontend integration pattern

Use a centralized API layer with typed request/response interfaces:

```ts
interface ApiResponse<T> {
  success: boolean;
  message?: string;
  data: T | null;
  errors?: Record<string, string[]>;
}
```

Then wrap each endpoint in a service function, for example:

```ts
const getPatientVisits = () => api.get<ApiResponse<Visit[]>>('/patient/visits');
const callNextTicket = (stationId: number) =>
  api.post<ApiResponse<QueueTicket>>(`/queue/stations/${stationId}/call-next`);
```

This keeps UI logic clean and avoids guessing the shape of backend payloads.

---

## 12. Critical validation checklist before frontend build

Before implementing screens, validate these with the backend:

1. current auth user role and station assignment
2. `department_code` exists and is active
3. current station belongs to current user
4. queue state transitions are valid and consistent
5. API response codes for `403`, `404`, `422`, `401`
6. patient-only routes are blocked from staff with `403`
7. queue display board route is public and read-only

This is the contract the frontend should be built against.
