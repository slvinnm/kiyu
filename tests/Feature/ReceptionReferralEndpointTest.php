<?php

use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Models\User;
use Tests\Support\ApiScenario;

it('registers a kiosk acquisition for a patient at reception', function (): void {
    $department = ApiScenario::department('RECEPTION-QUEUE');
    $station = ApiScenario::station($department, 'RECEPTION-REG');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Reception Patient']);
    $receptionist = User::factory()->create([
        'role' => UserRole::RECEPTIONIST,
        'department_id' => $department->id,
    ]);

    $acquisitionResponse = $this->postJson('/api/v1/kiosk/queue-acquisitions', [
        'department_code' => 'RECEPTION-QUEUE',
    ])->assertCreated();
    $acquisitionId = $acquisitionResponse->json('data.id');
    $acquisition = QueueAcquisition::findOrFail($acquisitionId);
    $visitId = $acquisitionResponse->json('data.visit.id');

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions/' . $acquisition . '/register', [
            'patient_id' => $patient->id,
        ])
        ->assertOk()
        ->assertJsonPath('data.visit.id', $visitId)
        ->assertJsonPath('data.status', 'ACQUIRED');

    expect($acquisition->fresh()->visit->patient_id)->toBe($patient->id);
    expect($acquisition->fresh()->visit->queueTickets()->sole()->status->value)->toBe('CREATED');
});

it('creates a direct walk-in visit for an existing patient', function (): void {
    $department = ApiScenario::department('DIRECT-WALKIN');
    $station = ApiScenario::station($department, 'DIRECT-WALKIN-REG');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Existing Walk-in Patient']);
    $receptionist = User::factory()->create(['role' => UserRole::RECEPTIONIST]);

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/visits', [
            'department_code' => $department->code,
            'patient_id' => $patient->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.intake_channel', 'WALK_IN')
        ->assertJsonPath('data.status', 'WAITING')
        ->assertJsonPath('data.queue_tickets.0.status', 'CREATED');

    expect($patient->fresh()->visits()->count())->toBe(1);
    expect($patient->fresh()->visits()->first()->queueAcquisition()->exists())->toBeTrue();
});

it('creates a patient and walk-in visit when reception receives new patient data', function (): void {
    $department = ApiScenario::department('NEW-WALKIN');
    $station = ApiScenario::station($department, 'NEW-WALKIN-REG');
    ApiScenario::workflow($department, [$station]);
    $receptionist = User::factory()->create(['role' => UserRole::RECEPTIONIST]);

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/visits', [
            'department_code' => $department->code,
            'name' => 'New Walk-in Patient',
            'phone' => '08123456789',
        ])
        ->assertCreated()
        ->assertJsonPath('data.intake_channel', 'WALK_IN');

    expect(Patient::query()->where('name', 'New Walk-in Patient')->exists())->toBeTrue();
});

it('allows reception registration from another department', function (): void {
    $department = ApiScenario::department('RECEPTION-SOURCE');
    $station = ApiScenario::station($department, 'RECEPTION-SOURCE-REG');
    ApiScenario::workflow($department, [$station]);
    $otherDepartment = ApiScenario::department('RECEPTION-OTHER');
    $receptionist = User::factory()->create([
        'role' => UserRole::RECEPTIONIST,
        'department_id' => $otherDepartment->id,
    ]);
    $patient = Patient::create(['name' => 'Unregistered Patient']);
    $acquisition = $this->postJson('/api/v1/kiosk/queue-acquisitions', [
        'department_code' => 'RECEPTION-SOURCE',
    ])->json('data.id');

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions/' . $acquisition . '/register', [
            'patient_id' => $patient->id,
        ])->assertOk();
});

it('creates and retrieves a referral for an authorized clinical user', function (): void {
    $sourceDepartment = ApiScenario::department('REFERRAL-SOURCE');
    $sourceStation = ApiScenario::station($sourceDepartment, 'REFERRAL-DOC', StationType::DOCTOR, 'D');
    ApiScenario::workflow($sourceDepartment, [$sourceStation]);
    $targetDepartment = ApiScenario::department('REFERRAL-TARGET');
    $targetStation = ApiScenario::station($targetDepartment, 'REFERRAL-LAB', StationType::LABORATORY, 'L');
    ApiScenario::workflow($targetDepartment, [$targetStation]);
    $patient = Patient::create(['name' => 'Referral Patient']);
    $visit = ApiScenario::visit($patient, $sourceDepartment);
    $doctor = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $sourceDepartment->id,
        'station_id' => $sourceStation->id,
    ]);

    $referral = $this->actingAs($doctor, 'sanctum')
        ->postJson('/api/v1/referrals/visits/' . $visit->id, [
            'target_department_id' => $targetDepartment->id,
            'reason' => 'Laboratory examination',
        ])
        ->assertCreated()
        ->assertJsonPath('data.target_department.id', $targetDepartment->id)
        ->json('data.id');

    $this->getJson('/api/v1/referrals/' . $referral)
        ->assertOk()
        ->assertJsonPath('data.id', $referral);
});

it('forbids a clinical user from another department from creating a referral', function (): void {
    $sourceDepartment = ApiScenario::department('REFERRAL-BOUNDARY-SOURCE');
    $sourceStation = ApiScenario::station($sourceDepartment, 'REFERRAL-BOUNDARY-DOC', StationType::DOCTOR, 'D');
    ApiScenario::workflow($sourceDepartment, [$sourceStation]);
    $targetDepartment = ApiScenario::department('REFERRAL-BOUNDARY-TARGET');
    $targetStation = ApiScenario::station($targetDepartment, 'REFERRAL-BOUNDARY-LAB', StationType::LABORATORY, 'L');
    ApiScenario::workflow($targetDepartment, [$targetStation]);
    $patient = Patient::create(['name' => 'Referral Boundary Patient']);
    $visit = ApiScenario::visit($patient, $sourceDepartment);
    $doctor = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $targetDepartment->id,
    ]);

    $this->actingAs($doctor, 'sanctum')
        ->postJson('/api/v1/referrals/visits/' . $visit->id, [
            'target_department_id' => $targetDepartment->id,
        ])->assertForbidden();
});
