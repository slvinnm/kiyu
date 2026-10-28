<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('kiosk walk-in creates patient-less visit, acquisition and registration queue ticket', function () {
    $response = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'priority' => 1,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.visit.patient_id', null)
        ->assertJsonPath('data.visit.status', VisitStatus::Waiting->value)
        ->assertJsonPath('data.visit.intake_channel', IntakeChannel::Kiosk->value)
        ->assertJsonPath('data.acquisition.status', QueueAcquisitionStatus::Acquired->value)
        ->assertJsonPath('data.ticket.status', QueueTicketStatus::Created->value)
        ->assertJsonPath('data.ticket.station_id', $this->registrationStation->id)
        ->assertJsonPath('data.ticket.queue_number', 'R001');

    $this->assertDatabaseHas('visits', [
        'patient_id' => null,
        'department_id' => $this->generalDepartment->id,
        'status' => VisitStatus::Waiting->value,
    ]);

    $this->assertDatabaseHas('queue_acquisitions', [
        'channel' => IntakeChannel::Kiosk->value,
        'status' => QueueAcquisitionStatus::Acquired->value,
    ]);

    $this->assertDatabaseHas('queue_tickets', [
        'queue_number' => 'R001',
        'status' => QueueTicketStatus::Created->value,
    ]);
});

test('manual intake creates patient-less visit and registration queue ticket', function () {
    $response = $this->postJson('/api/intake/manual', [
        'department_id' => $this->generalDepartment->id,
        'priority' => 2,
        'registered_by' => 'receptionist-1',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.visit.patient_id', null)
        ->assertJsonPath('data.visit.status', VisitStatus::Waiting->value)
        ->assertJsonPath('data.visit.priority', 2)
        ->assertJsonPath('data.ticket.priority', 2)
        ->assertJsonPath('data.ticket.status', QueueTicketStatus::Created->value);
});

test('online booking links existing patient, sets status AWAITING_CHECKIN, and creates NO queue ticket yet', function () {
    $response = $this->postJson('/api/intake/online', [
        'patient_id' => $this->samplePatient->id,
        'department_id' => $this->generalDepartment->id,
        'priority' => 1,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.visit.patient_id', $this->samplePatient->id)
        ->assertJsonPath('data.visit.status', VisitStatus::AwaitingCheckin->value)
        ->assertJsonPath('data.ticket', null);

    $visitId = $response->json('data.visit.id');

    // Confirm NO tickets exist for this visit yet
    $this->assertDatabaseMissing('queue_tickets', [
        'visit_id' => $visitId,
    ]);
});

test('online patient self check-in via kiosk transitions visit to WAITING and creates registration queue ticket', function () {
    // 1. Create online booking
    $bookingResponse = $this->postJson('/api/intake/online', [
        'patient_id' => $this->samplePatient->id,
        'department_id' => $this->generalDepartment->id,
    ]);

    $activeKey = $bookingResponse->json('data.visit.online_active_key');
    $visitId = $bookingResponse->json('data.visit.id');

    // 2. Perform online check-in using the active key
    $checkinResponse = $this->postJson('/api/intake/online-checkin', [
        'booking_identifier' => $activeKey,
    ]);

    $checkinResponse->assertStatus(200)
        ->assertJsonPath('data.visit.status', VisitStatus::Waiting->value)
        ->assertJsonPath('data.ticket.status', QueueTicketStatus::Created->value)
        ->assertJsonPath('data.ticket.station_id', $this->registrationStation->id);

    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::Waiting->value,
    ]);

    $this->assertDatabaseHas('queue_tickets', [
        'visit_id' => $visitId,
        'station_id' => $this->registrationStation->id,
        'status' => QueueTicketStatus::Created->value,
    ]);
});

test('online check-in rejects already checked-in visit', function () {
    $bookingResponse = $this->postJson('/api/intake/online', [
        'patient_id' => $this->samplePatient->id,
        'department_id' => $this->generalDepartment->id,
    ]);

    $activeKey = $bookingResponse->json('data.visit.online_active_key');

    // First check-in
    $this->postJson('/api/intake/online-checkin', [
        'booking_identifier' => $activeKey,
    ])->assertStatus(200);

    // Second check-in must fail
    $secondCheckin = $this->postJson('/api/intake/online-checkin', [
        'booking_identifier' => $activeKey,
    ]);

    $secondCheckin->assertStatus(422)
        ->assertJsonPath('error_type', 'INVALID_STATE');
});

test('idempotency key returns same result for identical payload and 409 for conflict', function () {
    $key = 'idemp-test-12345';

    // 1st call
    $res1 = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'idempotency_key' => $key,
    ])->assertStatus(201);

    $visitId1 = $res1->json('data.visit.id');

    // 2nd call with same parameters
    $res2 = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'idempotency_key' => $key,
    ])->assertStatus(201);

    expect($res2->json('data.visit.id'))->toBe($visitId1);

    // 3rd call with different parameters but same idempotency key -> conflict 409
    $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->dentalDepartment->id,
        'idempotency_key' => $key,
    ])->assertStatus(409);
});
