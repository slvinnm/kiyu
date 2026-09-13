<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\CreateVisit;
use App\Services\QueueService;
use Illuminate\Validation\ValidationException;

function queueConcurrencyFixture(): array
{
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL-CONCURRENCY',
        'is_active' => true,
    ]);

    $station = Station::create([
        'department_id' => $department->id,
        'name' => 'Registration',
        'code' => 'REG-CONCURRENCY',
        'type' => StationType::REGISTRATION,
        'queue_prefix' => 'A',
        'is_active' => true,
    ]);

    $workflow = Workflow::create([
        'department_id' => $department->id,
        'name' => 'Concurrency workflow',
        'is_active' => true,
    ]);

    $version = WorkflowVersion::create([
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'is_active' => true,
    ]);

    WorkflowStep::create([
        'workflow_version_id' => $version->id,
        'station_id' => $station->id,
        'name' => 'Registration',
        'sequence' => 1,
        'requires_queue' => true,
    ]);

    $patients = collect([
        Patient::create(['name' => 'Patient One']),
        Patient::create(['name' => 'Patient Two']),
    ]);

    return compact('department', 'station', 'workflow', 'version', 'patients');
}

it('does not call another ticket while a station has an active ticket', function () {
    $fixture = queueConcurrencyFixture();
    $createVisit = app(CreateVisit::class);
    $queue = app(QueueService::class);

    $firstVisit = $createVisit->handle(
        $fixture['patients'][0]->id,
        $fixture['department']->code,
        IntakeChannel::WALK_IN,
    );

    $secondVisit = $createVisit->handle(
        $fixture['patients'][1]->id,
        $fixture['department']->code,
        IntakeChannel::WALK_IN,
    );

    $firstTicket = $firstVisit->queueTickets()->sole();
    $secondTicket = $secondVisit->queueTickets()->sole();

    $called = $queue->callNext($fixture['station']->id);

    expect($called->id)->toBe($firstTicket->id);
    expect($firstTicket->fresh()->status)->toBe(QueueStatus::CALLED);

    expect(fn () => $queue->callNext($fixture['station']->id))
        ->toThrow(ValidationException::class);

    expect($secondTicket->fresh()->status)->toBe(QueueStatus::CREATED);
});

it('prevents a completed ticket from being completed twice', function () {
    $fixture = queueConcurrencyFixture();
    $visit = app(CreateVisit::class)->handle(
        $fixture['patients'][0]->id,
        $fixture['department']->code,
        IntakeChannel::WALK_IN,
    );

    $queue = app(QueueService::class);
    $ticket = $visit->queueTickets()->sole();

    $queue->callNext($fixture['station']->id);
    $queue->startTicket($ticket->id);
    $queue->completeTicket($ticket->id);

    expect($ticket->fresh()->status)->toBe(QueueStatus::COMPLETED);
    expect($visit->fresh()->status)->toBe(VisitStatus::COMPLETED);

    expect(fn () => $queue->completeTicket($ticket->id))
        ->toThrow(ValidationException::class);

    expect($ticket->fresh()->status)->toBe(QueueStatus::COMPLETED);
});
