<?php

use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Patient;
use App\Models\User;
use App\Services\QueueService;
use Tests\Support\ApiScenario;

it('lists all active stations for admins and only the assigned station for staff', function (): void {
    $department = ApiScenario::department('QUEUE-STATIONS');
    $first = ApiScenario::station($department, 'QUEUE-REG', StationType::REGISTRATION);
    $second = ApiScenario::station($department, 'QUEUE-NURSE', StationType::NURSE, 'T');
    ApiScenario::workflow($department, [$first]);
    $admin = User::factory()->create(['role' => UserRole::ADMIN]);
    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'department_id' => $department->id,
        'station_id' => $second->id,
    ]);

    $this->actingAs($admin, 'sanctum')
        ->getJson('/api/v1/queue/stations')
        ->assertOk()
        ->assertJsonCount(2, 'data');

    app('auth')->forgetGuards();
    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/v1/queue/stations')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.code', 'QUEUE-NURSE');
});

it('runs a queue ticket through call, start, hold, resume, and complete endpoints', function (): void {
    $department = ApiScenario::department('QUEUE-LIFECYCLE');
    $station = ApiScenario::station($department, 'QUEUE-LIFE');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Queue Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $ticket = $visit->queueTickets()->sole();
    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'department_id' => $department->id,
        'station_id' => $station->id,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson('/api/v1/queue/stations/' . $station->id . '/call-next')
        ->assertOk()
        ->assertJsonPath('data.id', $ticket->id);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')
        ->assertOk()
        ->assertJsonPath('data.status', QueueStatus::IN_PROGRESS->value);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/hold')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/resume')
        ->assertOk()
        ->assertJsonPath('success', true);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')->assertOk();
    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/complete')
        ->assertOk()
        ->assertJsonPath('data.ticket.status', QueueStatus::COMPLETED->value);
});

it('returns 422 when a ticket cannot be started from its current state', function (): void {
    $department = ApiScenario::department('QUEUE-INVALID');
    $station = ApiScenario::station($department, 'QUEUE-INVALID-STATION');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Invalid Queue Patient']);
    $ticket = ApiScenario::visit($patient, $department)->queueTickets()->sole();
    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'station_id' => $station->id,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')
        ->assertUnprocessable()
        ->assertJsonPath('success', false);
});

it('forbids staff from managing a ticket assigned to another station', function (): void {
    $department = ApiScenario::department('QUEUE-BOUNDARY');
    $station = ApiScenario::station($department, 'QUEUE-BOUNDARY-ONE');
    $otherStation = ApiScenario::station($department, 'QUEUE-BOUNDARY-TWO', StationType::DOCTOR, 'D');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Boundary Patient']);
    $ticket = ApiScenario::visit($patient, $department)->queueTickets()->sole();
    $staff = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'station_id' => $otherStation->id,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson('/api/v1/queue/tickets/' . $ticket->id . '/start')
        ->assertForbidden();
});

it('forbids patients from internal station endpoints', function (): void {
    $account = ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->getJson('/api/v1/queue/stations')
        ->assertForbidden();
});

it('cancels the visit runtime when a ticket is cancelled', function (): void {
    $department = ApiScenario::department('QUEUE-CANCEL-RUNTIME');
    $station = ApiScenario::station($department, 'QUEUE-CANCEL-RUNTIME-STATION');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Cancel Runtime Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $ticket = $visit->queueTickets()->sole();
    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'station_id' => $station->id,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson('/api/v1/queue/tickets/' . $ticket->id . '/cancel')
        ->assertOk();

    expect($visit->fresh()->status)->toBe(VisitStatus::CANCELLED);
    expect($visit->visitWorkflow->fresh()->status)->toBe(VisitWorkflowStatus::CANCELLED);
    expect($ticket->visitWorkflowStep->fresh()->status)->toBe(VisitWorkflowStepStatus::CANCELLED);
});

it('marks a called ticket as no-show and closes its visit runtime', function (): void {
    $department = ApiScenario::department('QUEUE-NOSHOW-RUNTIME');
    $station = ApiScenario::station($department, 'QUEUE-NOSHOW-RUNTIME-STATION');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'No-show Runtime Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $ticket = $visit->queueTickets()->sole();
    $staff = User::factory()->create([
        'role' => UserRole::STAFF,
        'station_id' => $station->id,
    ]);

    $this->actingAs($staff, 'sanctum');
    app(QueueService::class)->callNext($station->id, $staff->id);

    $this->postJson('/api/v1/queue/tickets/' . $ticket->id . '/no-show')
        ->assertOk();

    expect($ticket->fresh()->status)->toBe(QueueStatus::NO_SHOW);
    expect($visit->fresh()->status)->toBe(VisitStatus::CANCELLED);
});
