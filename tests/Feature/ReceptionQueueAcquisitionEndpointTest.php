<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\User;
use App\Services\QueueAcquisitionService;
use Tests\Support\ApiScenario;

it('lists active queue acquisitions for the selected department', function (): void {
    $department = ApiScenario::department('RECEPTION-LIST');
    $station = ApiScenario::station($department, 'RECEPTION-LIST-REG');
    ApiScenario::workflow($department, [$station]);

    $otherDepartment = ApiScenario::department('RECEPTION-LIST-OTHER');
    $otherStation = ApiScenario::station($otherDepartment, 'RECEPTION-LIST-OTHER-REG');
    ApiScenario::workflow($otherDepartment, [$otherStation]);

    $patient = Patient::create(['name' => 'Kiosk Patient']);
    $service = app(QueueAcquisitionService::class);
    $acquisition = $service->acquire($department->code);
    $service->attachPatient(
        acquisition: $acquisition,
        patient: $patient,
        registeredBy: User::factory()->create(['role' => UserRole::RECEPTIONIST]),
    );

    $otherAcquisition = $service->acquire($otherDepartment->code);
    $receptionist = User::factory()->create([
        'role' => UserRole::RECEPTIONIST,
        'department_id' => null,
        'station_id' => null,
    ]);

    $response = $this->actingAs($receptionist, 'sanctum')
        ->getJson('/api/v1/reception/queue-acquisitions?department_code=' . $department->code);

    $response
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $acquisition->id)
        ->assertJsonPath('data.0.queue_ticket.status', QueueStatus::CREATED->value)
        ->assertJsonPath('data.0.patient.name', 'Kiosk Patient');

    expect($otherAcquisition->fresh()->status)->toBe(QueueAcquisitionStatus::ACQUIRED);
});

it('lets reception call the next acquisition without a station assignment', function (): void {
    $department = ApiScenario::department('RECEPTION-CALL');
    $station = ApiScenario::station($department, 'RECEPTION-CALL-REG');
    ApiScenario::workflow($department, [$station]);

    $acquisition = app(QueueAcquisitionService::class)->acquire($department->code);
    $receptionist = User::factory()->create([
        'role' => UserRole::RECEPTIONIST,
        'department_id' => null,
        'station_id' => null,
    ]);

    $response = $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions/call-next', [
            'department_code' => $department->code,
        ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.ticket.id', $acquisition->visit->queueTickets()->sole()->id)
        ->assertJsonPath('data.ticket.status', QueueStatus::CALLED->value)
        ->assertJsonPath('data.acquisition.status', QueueAcquisitionStatus::ACQUIRED->value);
});

it('requires a patient before the registration ticket can be completed', function (): void {
    $department = ApiScenario::department('RECEPTION-COMPLETE');
    $station = ApiScenario::station($department, 'RECEPTION-COMPLETE-REG');
    ApiScenario::workflow($department, [$station]);

    $acquisition = app(QueueAcquisitionService::class)->acquire($department->code);
    $ticket = $acquisition->visit->queueTickets()->sole();
    $receptionist = User::factory()->create(['role' => UserRole::RECEPTIONIST]);

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions/call-next', [
            'department_code' => $department->code,
        ])
        ->assertOk();

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')
        ->assertOk()
        ->assertJsonPath('data.status', QueueStatus::IN_PROGRESS->value);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/complete')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['patient']);

    expect($acquisition->fresh()->status)->toBe(QueueAcquisitionStatus::ACQUIRED);
    expect($ticket->fresh()->status)->toBe(QueueStatus::IN_PROGRESS);
});

it('completes kiosk registration only after patient linkage', function (): void {
    $department = ApiScenario::department('RECEPTION-FULL');
    $registrationStation = ApiScenario::station($department, 'RECEPTION-FULL-REG');
    $nurseStation = ApiScenario::station($department, 'RECEPTION-FULL-NURSE', StationType::NURSE, 'B');
    ApiScenario::workflow($department, [$registrationStation, $nurseStation]);

    $patient = Patient::create(['name' => 'Reception Full Patient']);
    $receptionist = User::factory()->create([
        'role' => UserRole::RECEPTIONIST,
        'department_id' => null,
        'station_id' => null,
    ]);

    $acquisition = app(QueueAcquisitionService::class)->acquire($department->code);

    $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions/call-next', [
            'department_code' => $department->code,
        ])
        ->assertOk();

    $ticket = $acquisition->visit->queueTickets()->sole();

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')
        ->assertOk();

    $this->postJson('/api/v1/reception/queue-acquisitions/' . $acquisition->id . '/register', [
        'patient_id' => $patient->id,
    ])->assertOk();

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/complete')
        ->assertOk()
        ->assertJsonPath('data.visit_completed', false)
        ->assertJsonPath('data.next_step.name', 'RECEPTION-FULL-NURSE');

    expect($acquisition->fresh()->status)->toBe(QueueAcquisitionStatus::REGISTERED)
        ->and($acquisition->fresh()->registered_at)->not->toBeNull()
        ->and($acquisition->fresh()->visit->patient_id)->toBe($patient->id)
        ->and($acquisition->fresh()->visit->status)->toBe(VisitStatus::WAITING)
        ->and($acquisition->fresh()->visit->queueTickets()->count())->toBe(2);
});

it('creates a manual reception queue acquisition', function (): void {
    $department = ApiScenario::department('RECEPTION-MANUAL');
    $station = ApiScenario::station($department, 'RECEPTION-MANUAL-REG');
    ApiScenario::workflow($department, [$station]);

    $receptionist = User::factory()->create(['role' => UserRole::RECEPTIONIST]);

    $response = $this->actingAs($receptionist, 'sanctum')
        ->postJson('/api/v1/reception/queue-acquisitions', [
            'department_code' => $department->code,
            'name' => 'Manual Patient',
            'phone' => '08123456789',
        ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.channel', IntakeChannel::MANUAL->value)
        ->assertJsonPath('data.status', QueueAcquisitionStatus::ACQUIRED->value)
        ->assertJsonPath('data.patient.name', 'Manual Patient');
});

it('keeps an online acquisition out of the reception queue until check-in', function (): void {
    $department = ApiScenario::department('RECEPTION-ONLINE');
    $station = ApiScenario::station($department, 'RECEPTION-ONLINE-REG');
    ApiScenario::workflow($department, [$station]);

    $account = Tests\Support\ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->postJson('/api/v1/online/visits', [
            'department_code' => $department->code,
        ])
        ->assertCreated();

    $receptionist = User::factory()->create(['role' => UserRole::RECEPTIONIST]);

    $this->actingAs($receptionist, 'sanctum')
        ->getJson('/api/v1/reception/queue-acquisitions?department_code=' . $department->code)
        ->assertOk()
        ->assertJsonCount(0, 'data');

    $visit = $account['patient']->visits()->latest('id')->first();

    $this->actingAs($account['user'], 'sanctum')
        ->postJson('/api/v1/patient/visits/' . $visit->id . '/check-in')
        ->assertOk();

    $this->actingAs($receptionist, 'sanctum')
        ->getJson('/api/v1/reception/queue-acquisitions?department_code=' . $department->code)
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
