<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\CreateVisit;
use App\Services\QueueService;
use Illuminate\Validation\ValidationException;

function workflowFixture(): array
{
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL',
        'is_active' => true,
    ]);

    $registration = Station::create([
        'department_id' => $department->id,
        'name' => 'Registration',
        'code' => 'REG',
        'type' => StationType::REGISTRATION,
        'queue_prefix' => 'A',
        'is_active' => true,
    ]);

    $nurse = Station::create([
        'department_id' => $department->id,
        'name' => 'Nurse',
        'code' => 'NUR',
        'type' => StationType::NURSE,
        'queue_prefix' => 'T',
        'is_active' => true,
    ]);

    $doctor = Station::create([
        'department_id' => $department->id,
        'name' => 'Doctor',
        'code' => 'DOC',
        'type' => StationType::DOCTOR,
        'queue_prefix' => 'C',
        'is_active' => true,
    ]);

    $workflow = Workflow::create([
        'department_id' => $department->id,
        'name' => 'General workflow',
        'is_active' => true,
    ]);

    $version = WorkflowVersion::create([
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'is_active' => true,
    ]);

    $patient = Patient::create(['name' => 'Patient']);

    return compact('department', 'registration', 'nurse', 'doctor', 'workflow', 'version', 'patient');
}

function createWorkflowVisit(array $fixture, IntakeChannel $channel = IntakeChannel::WALK_IN)
{
    return app(CreateVisit::class)->handle(
        $fixture['patient']->id,
        $fixture['department']->code,
        $channel,
    );
}

it('creates queue tickets in deterministic sequence and completes the visit', function () {
    $fixture = workflowFixture();

    foreach ([
        [$fixture['registration'], 'Registration'],
        [$fixture['nurse'], 'Nurse'],
        [$fixture['doctor'], 'Doctor'],
    ] as $sequence => [$station, $name]) {
        WorkflowStep::create([
            'workflow_version_id' => $fixture['version']->id,
            'station_id' => $station->id,
            'name' => $name,
            'sequence' => $sequence + 1,
            'requires_queue' => true,
        ]);
    }

    $visit = createWorkflowVisit($fixture);
    $queue = app(QueueService::class);

    $firstTicket = $visit->queueTickets()->sole();
    expect($firstTicket->station_id)->toBe($fixture['registration']->id);

    $queue->callNext($fixture['registration']->id);
    $queue->startTicket($firstTicket->id);
    $queue->completeTicket($firstTicket->id);

    $secondTicket = $visit->queueTickets()->where('station_id', $fixture['nurse']->id)->sole();
    $queue->callNext($fixture['nurse']->id);
    $queue->startTicket($secondTicket->id);
    $queue->completeTicket($secondTicket->id);

    $thirdTicket = $visit->queueTickets()->where('station_id', $fixture['doctor']->id)->sole();
    $queue->callNext($fixture['doctor']->id);
    $queue->startTicket($thirdTicket->id);
    $queue->completeTicket($thirdTicket->id);

    expect($visit->fresh()->status)->toBe(VisitStatus::COMPLETED);
    expect($thirdTicket->fresh()->status)->toBe(QueueStatus::COMPLETED);
});

it('skips an optional conditional step and continues to the next queue', function () {
    $fixture = workflowFixture();

    $registrationStep = WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['registration']->id,
        'name' => 'Registration',
        'sequence' => 1,
        'requires_queue' => true,
    ]);

    $optionalStep = WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['nurse']->id,
        'name' => 'Laboratory',
        'sequence' => 2,
        'requires_queue' => true,
        'is_optional' => true,
        'entry_conditions' => [
            'all' => [
                ['path' => 'clinical.include_laboratory', 'operator' => 'equals', 'value' => true],
            ],
        ],
    ]);

    WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['doctor']->id,
        'name' => 'Doctor',
        'sequence' => 3,
        'requires_queue' => true,
    ]);

    $visit = createWorkflowVisit($fixture);
    $queue = app(QueueService::class);
    $firstTicket = $visit->queueTickets()->sole();

    $queue->callNext($fixture['registration']->id);
    $queue->startTicket($firstTicket->id);
    $queue->completeTicket($firstTicket->id, null, [
        'clinical' => ['include_laboratory' => false],
    ]);

    expect($visit->queueTickets()->where('station_id', $fixture['nurse']->id)->exists())->toBeFalse();
    expect($visit->queueTickets()->where('station_id', $fixture['doctor']->id)->exists())->toBeTrue();
    expect($visit->visitWorkflow->steps()->where('workflow_step_id', $optionalStep->id)->value('status'))
        ->toBe(VisitWorkflowStepStatus::SKIPPED);
});

it('creates a repeat execution when a repeatable step is explicitly requested', function () {
    $fixture = workflowFixture();

    WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['registration']->id,
        'name' => 'Registration',
        'sequence' => 1,
        'requires_queue' => true,
        'is_repeatable' => true,
    ]);

    $visit = createWorkflowVisit($fixture);
    $queue = app(QueueService::class);
    $firstTicket = $visit->queueTickets()->sole();

    $queue->callNext($fixture['registration']->id);
    $queue->startTicket($firstTicket->id);

    $result = $queue->completeTicket($firstTicket->id, null, [
        'repeat_current_step' => true,
    ]);

    expect($result['repeated'])->toBeTrue();
    expect($visit->queueTickets()->count())->toBe(2);
    expect($visit->visitWorkflow->steps()->where('workflow_step_id', $firstTicket->visitWorkflowStep->workflow_step_id)->count())
        ->toBe(2);

    $secondTicket = $visit->queueTickets()->latest('id')->first();
    expect($secondTicket->visitWorkflowStep->execution_number)->toBe(2);

    $queue->callNext($fixture['registration']->id);
    $queue->startTicket($secondTicket->id);
    $queue->completeTicket($secondTicket->id);

    expect($visit->fresh()->status)->toBe(VisitStatus::COMPLETED);
});

it('blocks completion when completion requirements are not satisfied', function () {
    $fixture = workflowFixture();

    WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['registration']->id,
        'name' => 'Registration',
        'sequence' => 1,
        'requires_queue' => true,
        'completion_requirements' => [
            'all' => [
                ['path' => 'clinical.registration_verified', 'operator' => 'equals', 'value' => true],
            ],
        ],
    ]);

    $visit = createWorkflowVisit($fixture);
    $queue = app(QueueService::class);
    $ticket = $visit->queueTickets()->sole();

    $queue->callNext($fixture['registration']->id);
    $queue->startTicket($ticket->id);

    expect(fn () => $queue->completeTicket($ticket->id, null, [
        'clinical' => ['registration_verified' => false],
    ]))->toThrow(ValidationException::class);

    expect($ticket->fresh()->status)->toBe(QueueStatus::IN_PROGRESS);
    expect($visit->fresh()->status)->toBe(VisitStatus::IN_PROGRESS);

    $queue->completeTicket($ticket->id, null, [
        'clinical' => ['registration_verified' => true],
    ]);

    expect($visit->fresh()->status)->toBe(VisitStatus::COMPLETED);
});

it('progresses the workflow when a skippable ticket is skipped', function () {
    $fixture = workflowFixture();

    WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['registration']->id,
        'name' => 'Registration',
        'sequence' => 1,
        'requires_queue' => true,
        'can_skip' => true,
    ]);

    WorkflowStep::create([
        'workflow_version_id' => $fixture['version']->id,
        'station_id' => $fixture['doctor']->id,
        'name' => 'Doctor',
        'sequence' => 2,
        'requires_queue' => true,
    ]);

    $visit = createWorkflowVisit($fixture);
    $queue = app(QueueService::class);
    $ticket = $visit->queueTickets()->sole();

    $result = $queue->skipTicket($ticket->id, null, 'Patient moved directly to doctor');

    expect($result['skipped'])->toBeTrue();
    expect($result['visit_completed'])->toBeFalse();
    expect($visit->queueTickets()->where('station_id', $fixture['doctor']->id)->exists())->toBeTrue();
});
