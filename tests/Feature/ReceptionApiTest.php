<?php

use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('reception call-next automatically resolves registration station and calls highest priority first', function () {
    // 1. Create NORMAL intake (seq 1)
    $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'priority' => Priority::Normal->value,
    ])->assertStatus(201);

    // 2. Create EMERGENCY intake (seq 2)
    $emergencyRes = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'priority' => Priority::Emergency->value,
    ])->assertStatus(201);

    $emergencyTicketId = $emergencyRes->json('data.ticket.id');

    // 3. Receptionist calls next
    $callResponse = $this->postJson('/api/reception/call-next', [
        'department_id' => $this->generalDepartment->id,
        'assigned_to' => 'receptionist-alice',
    ]);

    $callResponse->assertStatus(200)
        ->assertJsonPath('data.id', $emergencyTicketId)
        ->assertJsonPath('data.status', QueueTicketStatus::Called->value)
        ->assertJsonPath('data.priority', Priority::Emergency->value)
        ->assertJsonPath('data.assigned_to', 'receptionist-alice');

    // Verify QueueEvent was created
    $this->assertDatabaseHas('queue_events', [
        'queue_ticket_id' => $emergencyTicketId,
        'event_type' => 'CALLED',
        'from_status' => 'CREATED',
        'to_status' => 'CALLED',
        'user_id' => 'receptionist-alice',
    ]);
});

test('reception complete-registration links patient, completes ticket, creates next clinical ticket, and keeps visit WAITING', function () {
    // 1. Create kiosk intake
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];
    $visitId = $intake['visit']['id'];

    // 2. Call ticket
    $this->postJson('/api/reception/call-next', [
        'department_id' => $this->generalDepartment->id,
    ])->assertStatus(200);

    // 3. Complete registration linking existing patient
    $completeRes = $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $acqId,
        'patient_id' => $this->samplePatient->id,
        'registered_by' => 'receptionist-alice',
    ]);

    $completeRes->assertStatus(200)
        ->assertJsonPath('data.acquisition.status', QueueAcquisitionStatus::Registered->value)
        ->assertJsonPath('data.registration_ticket.status', QueueTicketStatus::Completed->value)
        ->assertJsonPath('data.visit.patient_id', $this->samplePatient->id)
        // CRITICAL INVARIANT: Visit status must remain WAITING!
        ->assertJsonPath('data.visit.status', VisitStatus::Waiting->value)
        ->assertJsonPath('data.next_ticket.status', QueueTicketStatus::Created->value)
        ->assertJsonPath('data.next_ticket.station_id', $this->nurseStation->id);

    // Verify in database
    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::Waiting->value,
        'patient_id' => $this->samplePatient->id,
    ]);

    $this->assertDatabaseHas('queue_acquisitions', [
        'id' => $acqId,
        'status' => QueueAcquisitionStatus::Registered->value,
    ]);

    $this->assertDatabaseHas('queue_tickets', [
        'visit_id' => $visitId,
        'station_id' => $this->nurseStation->id,
        'status' => QueueTicketStatus::Created->value,
    ]);
});

test('reception complete-registration can create a new patient on the fly', function () {
    $intake = $this->postJson('/api/intake/manual', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];

    $completeRes = $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $acqId,
        'patient_data' => [
            'name' => 'Siti Nurhaliza',
            'nik' => '3201015505950002',
            'phone' => '08987654321',
        ],
    ]);

    $completeRes->assertStatus(200)
        ->assertJsonPath('data.visit.patient.name', 'Siti Nurhaliza');

    $this->assertDatabaseHas('patients', [
        'name' => 'Siti Nurhaliza',
        'nik' => '3201015505950002',
    ]);
});

test('reception double-completion is prevented and returns conflict 409', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];

    // Call ticket
    $this->postJson('/api/reception/call-next', [
        'department_id' => $this->generalDepartment->id,
    ])->assertStatus(200);

    // 1st completion succeeds
    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $acqId,
        'patient_id' => $this->samplePatient->id,
    ])->assertStatus(200);

    // 2nd completion must fail with conflict (409)
    $secondRes = $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $acqId,
        'patient_id' => $this->samplePatient->id,
    ]);

    $secondRes->assertStatus(409)
        ->assertJsonPath('error_type', 'BUSINESS_CONFLICT');
});
