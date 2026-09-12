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

it('creates each queue ticket in workflow order and completes the visit', function () {
    $department = Department::create(['name' => 'General', 'code' => 'GENERAL']);
    $registration = Station::create(['department_id' => $department->id, 'name' => 'Registration', 'code' => 'REG', 'type' => StationType::REGISTRATION, 'queue_prefix' => 'A']);
    $nurse = Station::create(['department_id' => $department->id, 'name' => 'Nurse', 'code' => 'NUR', 'type' => StationType::NURSE, 'queue_prefix' => 'T']);
    $doctor = Station::create(['department_id' => $department->id, 'name' => 'Doctor', 'code' => 'DOC', 'type' => StationType::DOCTOR, 'queue_prefix' => 'C']);
    $workflow = Workflow::create(['department_id' => $department->id, 'name' => 'General workflow']);
    $version = WorkflowVersion::create(['workflow_id' => $workflow->id, 'version_number' => 1, 'is_active' => true]);

    foreach ([[$registration, 'Registration'], [$nurse, 'Nurse'], [$doctor, 'Doctor']] as $sequence => [$station, $name]) {
        WorkflowStep::create([
            'workflow_version_id' => $version->id,
            'station_id' => $station->id,
            'name' => $name,
            'sequence' => $sequence + 1,
            'requires_queue' => true,
        ]);
    }

    $patient = Patient::create(['name' => 'Patient']);
    $visit = app(CreateVisit::class)->handle($patient->id, $department->code, IntakeChannel::WALK_IN);
    $queue = app(QueueService::class);

    $firstTicket = $visit->queueTickets()->sole();
    expect($firstTicket->station_id)->toBe($registration->id);

    $queue->callNext($registration->id);
    $queue->startTicket($firstTicket->id);
    $queue->completeTicket($firstTicket->id);

    $secondTicket = $visit->queueTickets()->where('station_id', $nurse->id)->sole();
    $queue->callNext($nurse->id);
    $queue->startTicket($secondTicket->id);
    $queue->completeTicket($secondTicket->id);

    $thirdTicket = $visit->queueTickets()->where('station_id', $doctor->id)->sole();
    $queue->callNext($doctor->id);
    $queue->startTicket($thirdTicket->id);
    $queue->completeTicket($thirdTicket->id);

    expect($visit->fresh()->status)->toBe(VisitStatus::COMPLETED);
    expect($thirdTicket->fresh()->status)->toBe(QueueStatus::COMPLETED);
});
