<?php

use App\Enums\Priority;
use App\Enums\QueueTicketStatus;
use App\Enums\ReferralStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\SetupKiyuHospital;

uses(RefreshDatabase::class, SetupKiyuHospital::class);

beforeEach(function () {
    $this->setupHospital();
});

test('referral creates target visit in target department and initial queue ticket', function () {
    // 1. Create visit in General Department
    $intake = $this->postJson('/api/intake/online', [
        'patient_id' => $this->samplePatient->id,
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    $sourceVisitId = $intake['visit']['id'];

    // 2. Create Referral to Dental Department
    $referralRes = $this->postJson('/api/referrals', [
        'source_visit_id' => $sourceVisitId,
        'target_department_id' => $this->dentalDepartment->id,
        'priority' => Priority::Priority->value,
        'reason' => 'Patient needs dental extraction',
        'referred_by' => 'doctor-alice',
    ]);

    $referralRes->assertStatus(201)
        ->assertJsonPath('data.referral.source_visit_id', $sourceVisitId)
        ->assertJsonPath('data.referral.target_department_id', $this->dentalDepartment->id)
        ->assertJsonPath('data.referral.status', ReferralStatus::Accepted->value)
        ->assertJsonPath('data.target_visit.department_id', $this->dentalDepartment->id)
        ->assertJsonPath('data.target_visit.patient_id', $this->samplePatient->id)
        ->assertJsonPath('data.target_ticket.status', QueueTicketStatus::Created->value);

    // Verify rejection of referral to same department
    $sameDeptRes = $this->postJson('/api/referrals', [
        'source_visit_id' => $sourceVisitId,
        'target_department_id' => $this->generalDepartment->id,
    ]);

    $sameDeptRes->assertStatus(422);
});

test('updating visit priority propagates to active tickets only and preserves historical tickets', function () {
    // Create walk-in visit
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
        'priority' => Priority::Normal->value,
    ])->json('data');

    $visitId = $intake['visit']['id'];

    // Call registration ticket
    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);

    // Complete registration -> Registration ticket becomes COMPLETED, Nurse ticket is created
    $this->postJson('/api/reception/complete-registration', [
        'acquisition_id' => $intake['acquisition']['id'],
        'patient_id' => $this->samplePatient->id,
    ]);

    // Active ticket is now the Nurse ticket (CREATED, priority = 1)
    // Registration ticket is COMPLETED (priority = 1)

    // Elevate Visit Priority to EMERGENCY (3)
    $priorityRes = $this->patchJson("/api/visits/{$visitId}/priority", [
        'priority' => Priority::Emergency->value,
        'user_id' => 'admin-carol',
    ]);

    $priorityRes->assertStatus(200)
        ->assertJsonPath('data.priority', Priority::Emergency->value);

    // Active Nurse ticket must have updated to EMERGENCY (3)
    $this->assertDatabaseHas('queue_tickets', [
        'visit_id' => $visitId,
        'station_id' => $this->nurseStation->id,
        'priority' => Priority::Emergency->value,
        'status' => QueueTicketStatus::Created->value,
    ]);

    // Historical Registration ticket must STILL have priority NORMAL (1)
    $this->assertDatabaseHas('queue_tickets', [
        'visit_id' => $visitId,
        'station_id' => $this->registrationStation->id,
        'priority' => Priority::Normal->value,
        'status' => QueueTicketStatus::Completed->value,
    ]);

    // Verify AuditLog was recorded
    $this->assertDatabaseHas('audit_logs', [
        'action' => 'VISIT_PRIORITY_CHANGED',
        'entity_id' => (string) $visitId,
        'user_id' => 'admin-carol',
    ]);
});

test('public queue display exposes operational numbers without any patient PII', function () {
    // 1. Create walk-in
    $intake = $this->postJson('/api/intake/kiosk', [
        'department_id' => $this->generalDepartment->id,
    ])->json('data');

    // 2. Call ticket
    $this->postJson('/api/reception/call-next', ['department_id' => $this->generalDepartment->id]);

    // 3. Query public queue
    $publicRes = $this->getJson('/api/public/queue');

    $publicRes->assertStatus(200);

    $json = $publicRes->getContent();

    // Verify NO patient PII exists in the public queue response
    expect($json)->not->toContain($this->samplePatient->name)
        ->not->toContain($this->samplePatient->mrn)
        ->not->toContain($this->samplePatient->nik)
        ->not->toContain($this->samplePatient->phone);

    $regStationDisplay = collect($publicRes->json('data'))->firstWhere('station_id', $this->registrationStation->id);
    expect($regStationDisplay)->not->toBeNull();
    expect($regStationDisplay['current_calling']['queue_number'])->toBe('R001');
    expect($regStationDisplay['current_calling']['status'])->toBe(QueueTicketStatus::Called->value);
});
