# Frontend API Contract — KIYU v1

**Base URL:** `http://localhost:8000/api/v1`
**Version:** v1 (prefix `/api/v1` in all routes)
**Auth:** Laravel Sanctum bearer tokens (`Authorization: Bearer <token>`)
**Content-Type:** `application/json`

---

## 1. Authentication

| Flow | Endpoint | Method | Throttle |
|------|----------|--------|----------|
| Patient register | `/patient/register` | POST | 10 req/min |
| Patient login | `/patient/login` | POST | 10 req/min |
| Get authenticated user | `/patient/me` | GET | 60 req/min |
| Get current user (any role) | `/user` | GET | 60 req/min |

**Token lifecycle:** Tokens issued on `/patient/login` with `abilities: ['*']`. Revoked on logout (not implemented) or when user deleted. Include `Accept: application/json` header.

---

## 2. Error Contract

All API errors return JSON with this envelope:

```json
{
  "success": false,
  "message": "Human-readable message",
  "errors": {} | []
}
```

| HTTP | Trigger | Message Source |
|------|---------|----------------|
| 400 | Bad Request (HttpException) | Exception message |
| 401 | Missing/invalid token | "Unauthenticated." |
| 403 | Policy denial | Policy message or "This action is unauthorized." |
| 404 | ModelNotFound / route not found | "The requested resource was not found." |
| 422 | ValidationException | "The given data was invalid." + `errors` object |
| 422 | **LogicException** (domain rule) | Exception message (e.g., "Only active queue tickets can be transferred.") |
| 429 | Throttle exceeded | "Too many requests." |
| 500 | Unexpected Throwable | "An unexpected error occurred." (message hidden) |

**Note:** `LogicException` is mapped to 422 in `bootstrap/app.php` so domain/business-rule violations return stable, user-facing messages instead of generic 500.

---

## 3. Rate Limits

| Group | Prefix | Limit |
|-------|--------|-------|
| Kiosk | `/kiosk/*` | 60 req/min |
| Public display | `/public/*` | 120 req/min |
| Patient auth (register/login) | `/patient/register`, `/patient/login` | 10 req/min |
| **Authenticated patient** | `/patient/*` (auth:sanctum) | **60 req/min** |
| Reception / Queue / Priority / Referrals | (auth:sanctum, no extra throttle) | unbounded |

---

## 4. Pagination Policy

**Current implementation:** All list endpoints return **unpaginated collections** using `Model::all()` or `->get()` (sometimes with `->limit(20)`). No `Link` headers, no `meta.pagination`. This is **intentional for near-term simplicity** (small datasets, internal tooling). Frontend should not rely on pagination; if/when pagination is added, it will use Laravel's standard `paginate()` envelope with `data`, `links`, `meta`.

Endpoints returning collections:
- `GET /patient/visits`
- `GET /reception/patients`
- `GET /queue/stations`
- `GET /public/queues/{station}` (returns single object with `upcoming` array, not a list)

---

## 5. Endpoint Inventory

### 5.1 Kiosk (public kiosk terminals)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| GET | `/kiosk/departments` | `KioskController@departments` | none | 60/min |
| POST | `/kiosk/queue-acquisitions` | `KioskController@acquire` | none | 60/min |

### 5.2 Public Display (waiting room screens)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| GET | `/public/queues/{station}` | `PublicQueueDisplayController@show` | none | 120/min |

### 5.3 Patient Auth
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| POST | `/patient/register` | `PatientAuthController@register` | none | 10/min |
| POST | `/patient/login` | `PatientAuthController@login` | none | 10/min |
| GET | `/patient/me` | `PatientAuthController@me` | sanctum | 60/min |
| GET | `/user` | Closure | sanctum | 60/min |

### 5.4 Patient Visits (authenticated patient)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| GET | `/patient/visits` | `PatientVisitController@index` | sanctum | 60/min |
| GET | `/patient/visits/{visit}` | `PatientVisitController@show` | sanctum + policy | 60/min |
| GET | `/patient/queue` | `PatientVisitController@queue` | sanctum | 60/min |
| POST | `/patient/visits/{visit}/check-in` | `PatientVisitController@checkIn` | sanctum + policy | 60/min |

### 5.5 Online Visits (patient self-scheduling)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| POST | `/online/visits` | `OnlineController@store` | sanctum (PATIENT role) | 60/min |

### 5.6 Reception (staff)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| GET | `/reception/patients` | `ReceptionController@patients` | sanctum (ADMIN/RECEPTIONIST) | — |
| POST | `/reception/visits` | `ReceptionController@store` | sanctum (ADMIN/RECEPTIONIST) | — |
| POST | `/reception/queue-acquisitions/{qa}/register` | `ReceptionController@registerQueueAcquisition` | sanctum (ADMIN/RECEPTIONIST same dept) | — |

### 5.7 Queue Operations (staff at stations)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| GET | `/queue/stations` | `QueueController@stations` | sanctum | — |
| POST | `/queue/stations/{station}/call-next` | `QueueController@callNext` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/start` | `QueueController@start` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/complete` | `QueueController@complete` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/hold` | `QueueController@hold` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/resume` | `QueueController@resume` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/skip` | `QueueController@skip` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/no-show` | `QueueController@noShow` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/cancel` | `QueueController@cancel` | sanctum + policy | — |
| POST | `/queue/tickets/{queueTicket}/transfer` | `QueueController@transfer` | sanctum + policy | — |

### 5.8 Priority Management
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| PATCH | `/priority/visits/{visit}` | `VisitPriorityController@update` | sanctum (ADMIN or DOCTOR/NURSE same dept) | — |

### 5.9 Referrals (cross-department)
| Method | Path | Controller | Auth | Throttle |
|--------|------|------------|------|----------|
| POST | `/referrals/visits/{visit}` | `ReferralController@store` | sanctum (ADMIN or DOCTOR/NURSE same dept) | — |
| GET | `/referrals/{referral}` | `ReferralController@show` | sanctum (ADMIN or DOCTOR/NURSE same dept) | — |

---

## 6. Request / Response Schemas

All enums are serialized as their **string value** (e.g., `"status": "WAITING"`).

### 6.1 Enums (reference)

```text
QueueStatus:          CREATED | CALLED | IN_PROGRESS | COMPLETED | ON_HOLD | SKIPPED | NO_SHOW | CANCELLED | TRANSFERRED
VisitStatus:          AWAITING_CHECKIN | WAITING | CHECKED_IN | IN_PROGRESS | COMPLETED | CANCELLED
VisitWorkflowStatus:  ACTIVE | COMPLETED | SKIPPED
VisitWorkflowStepStatus: PENDING | ACTIVE | COMPLETED | SKIPPED | FAILED
Priority:             NORMAL (1) | PRIORITY (2) | EMERGENCY (3)
UserRole:             ADMIN | DOCTOR | NURSE | RECEPTIONIST | PHARMACIST | LAB_TECH | RADIOLOGIST | PATIENT
StationType:          REGISTRATION | TRIAGE | CONSULTATION | PHARMACY | LABORATORY | RADIOLOGY
IntakeChannel:        ONLINE | KIOSK | WALK_IN
ReferralStatus:       PENDING | ACCEPTED | REJECTED | COMPLETED
```

### 6.2 PatientRegisterRequest — POST /patient/register
```json
{
  "name": "string (required, max:255)",
  "email": "string (required, email, unique:users)",
  "password": "string (required, min:8, confirmed)",
  "national_id": "string (nullable, unique:patients)",
  "date_of_birth": "date (nullable)",
  "gender": "string (nullable, in:male,female)",
  "phone": "string (nullable, max:20)",
  "address": "string (nullable, max:255)"
}
```
**Response 201:** `PatientResource` + `token` string

### 6.3 PatientLoginRequest — POST /patient/login
```json
{
  "email": "string (required, email)",
  "password": "string (required)"
}
```
**Response 200:** `{ "token": "<sanctum-token>", "user": PatientResource }`

### 6.4 OnlineVisitRequest — POST /online/visits
```json
{
  "department_code": "string (required, exists:departments,code where is_active)"
}
```
**Response 201:** `VisitResource` (with `queue_tickets` collection)

### 6.5 AcquireQueueRequest — POST /kiosk/queue-acquisitions
```json
{
  "department_code": "string (required, exists:departments,code where is_active)",
  "idempotency_key": "string (nullable, uuid, unique:queue_acquisitions)"
}
```
**Response 201:** `QueueAcquisitionResource`

### 6.6 ReceptionVisitRequest — POST /reception/visits
```json
{
  "department_code": "string (required, exists:departments,code where is_active)",
  "patient_id": "integer (nullable, exists:patients,id, required_without:name)",
  "name": "string (required_without:patient_id, max:255)",
  "email": "string (nullable, email, max:255, required_without:patient_id)",
  "national_id": "string (nullable, unique:patients,national_id, required_without:patient_id)",
  "date_of_birth": "date (nullable, required_without:patient_id)",
  "gender": "string (nullable, in:male,female, required_without:patient_id)",
  "phone": "string (nullable, max:20, required_without:patient_id)",
  "address": "string (nullable, max:255, required_without:patient_id)"
}
```
**Response 201:** `VisitResource`

### 6.7 ReceptionRegisterQueueAcquisitionRequest — POST /reception/queue-acquisitions/{qa}/register
```json
{
  "patient_id": "integer (required, exists:patients,id)"
}
```
**Response 201:** `VisitResource`

### 6.8 QueueCompleteRequest — POST /queue/tickets/{queueTicket}/complete
```json
{
  "completion_context": "array (nullable)"
}
```

### 6.9 QueueSkipRequest — POST /queue/tickets/{queueTicket}/skip
```json
{
  "reason": "string (nullable, max:1000)",
  "context": "array (nullable)"
}
```

### 6.10 QueueTransferRequest — POST /queue/tickets/{queueTicket}/transfer
```json
{
  "target_station_id": "integer (required, exists:stations,id where is_active)"
}
```
**Response 200:**
```json
{
  "success": true,
  "data": {
    "original": QueueTicketResource,
    "new": QueueTicketResource
  }
}
```

### 6.11 CreateReferralRequest — POST /referrals/visits/{visit}
```json
{
  "target_department_id": "integer (required, exists:departments,id where is_active)",
  "reason": "string (nullable, max:2000)",
  "priority": "integer (nullable, in:1,2,3)"
}
```
**Response 201:** `ReferralResource` (includes `target_visit` when created)

### 6.12 UpdateVisitPriorityRequest — PATCH /priority/visits/{visit}
```json
{
  "priority": "integer (required, in:1,2,3)"
}
```

---

### 6.13 Resources (Response Shapes)

#### PatientResource
```json
{
  "id": 1,
  "medical_record_number": "MRN-000001",
  "name": "John Doe",
  "date_of_birth": "1990-01-15",
  "gender": "male",
  "phone": "+628123456789",
  "email": "john@example.com",
  "address": "Jl. Contoh No. 123"
}
```

#### VisitResource
```json
{
  "id": 1,
  "visit_number": "V-20260913-0001",
  "status": "WAITING",
  "priority": "NORMAL",
  "intake_channel": "WALK_IN",
  "department": { "id": 1, "code": "POLI_UMUM", "name": "Poli Umum" },
  "queue_tickets": [ QueueTicketResource, ... ],
  "checked_in_at": "2026-09-13T08:30:00.000000Z",
  "completed_at": null
}
```

#### QueueTicketResource
```json
{
  "id": 1,
  "queue_number": "A-001",
  "status": "CREATED",
  "priority": "NORMAL",
  "internal_sequence": 1,
  "station": { "id": 1, "code": "REG-01", "name": "Registrasi 1", "type": "REGISTRATION" },
  "visit": { "id": 1, "visit_number": "V-20260913-0001", "status": "WAITING" },
  "workflow_step": { "id": 1, "name": "Registrasi", "sequence": 1 },
  "called_at": null,
  "started_at": null,
  "completed_at": null
}
```

#### QueueAcquisitionResource
```json
{
  "id": 1,
  "status": "PENDING",
  "channel": "KIOSK",
  "acquired_at": "2026-09-13T08:00:00.000000Z",
  "department": { "id": 1, "code": "POLI_UMUM", "name": "Poli Umum" },
  "visit": { "id": 1, "visit_number": "V-20260913-0001" },
  "queue_ticket": { "id": 1, "queue_number": "A-001", "status": "CREATED", "priority": "NORMAL", "station_id": 1 }
}
```

#### StationResource
```json
{
  "id": 1,
  "code": "REG-01",
  "name": "Registrasi 1",
  "type": "REGISTRATION",
  "queue_prefix": "A",
  "is_active": true,
  "department": { "id": 1, "code": "POLI_UMUM", "name": "Poli Umum" }
}
```

#### WorkflowStepResource
```json
{
  "id": 1,
  "name": "Registrasi",
  "sequence": 1,
  "requires_queue": true,
  "is_optional": false,
  "is_repeatable": false,
  "can_skip": false
}
```

#### PatientQueueTicketResource (for `/patient/queue`)
```json
{
  "id": 1,
  "queue_number": "A-001",
  "status": "CREATED",
  "priority": "NORMAL",
  "queue_position": 3,
  "station": { "id": 1, "code": "REG-01", "name": "Registrasi 1", "type": "REGISTRATION" },
  "department": { "id": 1, "code": "POLI_UMUM", "name": "Poli Umum" },
  "visit": { "id": 1, "visit_number": "V-20260913-0001", "status": "WAITING" },
  "workflow_step": { "id": 1, "name": "Registrasi", "sequence": 1 },
  "called_at": null,
  "started_at": null
}
```

#### PublicQueueDisplayResource (for `/public/queues/{station}`)
```json
{
  "station": { "code": "REG-01", "name": "Registrasi 1", "type": "REGISTRATION" },
  "department": { "code": "POLI_UMUM", "name": "Poli Umum" },
  "current": { "queue_number": "A-001", "status": "IN_PROGRESS", "called_at": "...", "started_at": "..." } | null,
  "upcoming": [
    { "queue_number": "A-002", "status": "CREATED" },
    { "queue_number": "A-003", "status": "CREATED" }
  ]
}
```

#### ReferralResource
```json
{
  "id": 1,
  "status": "ACCEPTED",
  "priority": "NORMAL",
  "reason": "Need specialist consultation",
  "source_visit": { "id": 1, "visit_number": "V-20260913-0001", "department": { "id": 1, "code": "POLI_UMUM", "name": "Poli Umum" } },
  "target_department": { "id": 2, "code": "POLI_JANTUNG", "name": "Poli Jantung" },
  "target_visit": {
    "id": 2,
    "visit_number": "V-20260913-0002",
    "status": "WAITING",
    "queue_tickets": [ QueueTicketResource, ... ]
  } | null,
  "referred_by_user_id": 5,
  "created_at": "2026-09-13T09:15:00.000000Z"
}
```

---

## 7. Status Code Summary

| Code | Meaning |
|------|---------|
| 200 | OK (GET, PATCH, POST actions returning data) |
| 201 | Created (POST registering resources) |
| 400 | Bad Request (malformed JSON, missing required fields caught before validation) |
| 401 | Unauthenticated (missing/invalid/expired token) |
| 403 | Forbidden (policy denied) |
| 404 | Not Found (model not found or route unknown) |
| 422 | Validation failed OR domain rule violation (LogicException) |
| 429 | Too Many Requests (throttle exceeded) |
| 500 | Internal Server Error (unexpected exception, message hidden) |

---

## 8. Versioning & Stability

- Version locked to **v1** via route prefix `/api/v1`.
- Breaking changes will introduce `/api/v2` prefix; v1 will be maintained for a transition period.
- Enum values are **stable strings**; new cases may be added but existing values never change.
- Resource shapes are **additive only** — new optional keys may appear; existing keys never removed or renamed.

---

## 9. Changelog

| Date | Change |
|------|--------|
| 2026-09-13 | Initial contract generated from codebase (FIND-004) |
| 2026-09-13 | Added `throttle:60,1` to authenticated patient group (FIND-005) |
| 2026-09-13 | LogicException → 422 mapping documented (FIND-001 fix) |

---

*Generated from source: `routes/api.php`, `bootstrap/app.php`, `app/Http/Resources/*.php`, `app/Http/Requests/*.php`, `app/Services/ReferralService.php`. Keep in sync with code.*