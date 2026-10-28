<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\VisitStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('queue acquisitions can be listed with filters', function () {
    // 1. Create kiosk intake
    $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->assertStatus(201);

    // 2. Create online booking
    $this->postJson('/api/intake/online', [
        'patient_id' => $this->samplePatient->id,
        'department_id' => $this->generalDepartment->id,
    ])->assertStatus(201);

    // List all
    $response = $this->getJson('/api/queue-acquisitions');
    $response->assertStatus(200)
        ->assertJsonPath('message', 'Queue acquisitions retrieved.')
        ->assertJsonCount(2, 'data.data');

    // Filter by channel = ONLINE
    $onlineResponse = $this->getJson('/api/queue-acquisitions?channel=ONLINE');
    $onlineResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.channel', IntakeChannel::Online->value);

    // Filter by channel = KIOSK
    $kioskResponse = $this->getJson('/api/queue-acquisitions?channel=KIOSK');
    $kioskResponse->assertStatus(200)
        ->assertJsonCount(1, 'data.data')
        ->assertJsonPath('data.data.0.channel', IntakeChannel::Kiosk->value);
});

test('queue acquisition can be fetched by ID with full details', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];

    $response = $this->getJson("/api/queue-acquisitions/{$acqId}");
    $response->assertStatus(200)
        ->assertJsonPath('message', 'Queue acquisition retrieved.')
        ->assertJsonPath('data.id', $acqId)
        ->assertJsonPath('data.channel', IntakeChannel::Kiosk->value)
        ->assertJsonPath('data.status', QueueAcquisitionStatus::Acquired->value);
});

test('queue acquisition can be cancelled and transitions visit to CANCELLED', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];
    $visitId = $intake['visit']['id'];

    $cancelRes = $this->postJson("/api/queue-acquisitions/{$acqId}/cancel", [
        'reason' => 'Patient decided not to wait',
        'user_id' => 'kiosk-admin',
    ]);

    $cancelRes->assertStatus(200)
        ->assertJsonPath('message', 'Queue acquisition cancelled.')
        ->assertJsonPath('data.status', QueueAcquisitionStatus::Cancelled->value);

    $this->assertDatabaseHas('queue_acquisitions', [
        'id' => $acqId,
        'status' => QueueAcquisitionStatus::Cancelled->value,
    ]);

    $this->assertDatabaseHas('visits', [
        'id' => $visitId,
        'status' => VisitStatus::Cancelled->value,
    ]);

    // Check audit log
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'ACQUISITION_CANCELLED',
        'entity_type' => 'QueueAcquisition',
        'entity_id' => (string) $acqId,
    ]);
});

test('cannot cancel an acquisition that has already completed registration', function () {
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $acqId = $intake['acquisition']['id'];

    $this->postJson('/api/reception/call-next', [
        'department_id' => $this->generalDepartment->id,
    ])->assertStatus(200);

    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $acqId,
        'patient_id' => $this->samplePatient->id,
    ])->assertStatus(200);

    // Now try cancelling acquisition
    $cancelRes = $this->postJson("/api/queue-acquisitions/{$acqId}/cancel", [
        'reason' => 'Too late',
    ]);

    $cancelRes->assertStatus(409)
        ->assertJsonPath('error_type', 'BUSINESS_CONFLICT');
});
