<?php

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\Patient;
use Tests\Support\ApiScenario;

it('lists only active departments with active workflows for the kiosk', function (): void {
    $active = ApiScenario::department('ACTIVE-KIOSK');
    $activeStation = ApiScenario::station($active, 'ACTIVE-REG');
    ApiScenario::workflow($active, [$activeStation]);

    $withoutWorkflow = ApiScenario::department('NO-WORKFLOW-KIOSK');
    ApiScenario::station($withoutWorkflow, 'NO-WORKFLOW-REG');

    $inactive = ApiScenario::department('INACTIVE-KIOSK', false);
    $inactiveStation = ApiScenario::station($inactive, 'INACTIVE-REG');
    ApiScenario::workflow($inactive, [$inactiveStation]);

    $this->getJson('/api/v1/kiosk/departments')
        ->assertOk()
        ->assertJsonPath('data.0.code', 'ACTIVE-KIOSK')
        ->assertJsonMissing(['code' => 'NO-WORKFLOW-KIOSK'])
        ->assertJsonMissing(['code' => 'INACTIVE-KIOSK']);
});

it('acquires a kiosk queue and returns the same acquisition for an idempotency key', function (): void {
    $department = ApiScenario::department('ACQUIRE-KIOSK');
    $station = ApiScenario::station($department, 'ACQUIRE-REG');
    ApiScenario::workflow($department, [$station]);
    $payload = [
        'department_code' => 'ACQUIRE-KIOSK',
        'idempotency_key' => '123e4567-e89b-12d3-a456-426614174000',
    ];

    $first = $this->postJson('/api/v1/kiosk/queue-acquisitions', $payload);
    $first->assertCreated()->assertJsonPath('success', true);

    $second = $this->postJson('/api/v1/kiosk/queue-acquisitions', $payload);
    $second->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));
});

it('rejects kiosk acquisition for an inactive or unknown department', function (): void {
    $this->postJson('/api/v1/kiosk/queue-acquisitions', [
        'department_code' => 'UNKNOWN-DEPARTMENT',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['department_code']);
});

it('shows the current and priority-ordered upcoming queue publicly', function (): void {
    $department = ApiScenario::department('DISPLAY-QUEUE');
    $station = ApiScenario::station($department, 'DISPLAY-REG');
    ApiScenario::workflow($department, [$station]);
    $firstPatient = Patient::create(['name' => 'First Patient']);
    $secondPatient = Patient::create(['name' => 'Second Patient']);
    $thirdPatient = Patient::create(['name' => 'Third Patient']);

    $currentVisit = ApiScenario::visit($firstPatient, $department, IntakeChannel::WALK_IN);
    $priorityVisit = ApiScenario::visit($secondPatient, $department, IntakeChannel::WALK_IN);
    $normalVisit = ApiScenario::visit($thirdPatient, $department, IntakeChannel::WALK_IN);
    $currentTicket = $currentVisit->queueTickets()->sole();
    $priorityTicket = $priorityVisit->queueTickets()->sole();
    $normalTicket = $normalVisit->queueTickets()->sole();
    $currentTicket->update(['status' => QueueStatus::CALLED]);
    $priorityTicket->update(['priority' => Priority::PRIORITY]);

    $this->getJson('/api/v1/public/queues/DISPLAY-REG?limit=2')
        ->assertOk()
        ->assertJsonPath('data.station.code', 'DISPLAY-REG')
        ->assertJsonPath('data.current.queue_number', $currentTicket->queue_number)
        ->assertJsonPath('data.upcoming.0.queue_number', $priorityTicket->queue_number)
        ->assertJsonPath('data.upcoming.1.queue_number', $normalTicket->queue_number);
});
