<?php

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Department;
use App\Models\QueueEvent;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\QueueAcquisitionService;
use App\Services\QueueService;
use Illuminate\Validation\ValidationException;

function kioskCancellationFixture(): array
{
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL-KIOSK-CANCEL',
        'is_active' => true,
    ]);

    $station = Station::create([
        'department_id' => $department->id,
        'name' => 'Registration',
        'code' => 'REG-KIOSK-CANCEL',
        'type' => StationType::REGISTRATION,
        'queue_prefix' => 'K',
        'is_active' => true,
    ]);

    $workflow = Workflow::create([
        'department_id' => $department->id,
        'name' => 'Kiosk cancellation workflow',
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

    return compact('department', 'station');
}

it('cancels the kiosk acquisition and all active workflow state atomically', function () {
    $fixture = kioskCancellationFixture();

    $service = app(QueueAcquisitionService::class);
    $acquisition = $service->acquire($fixture['department']->code);

    $visit = $acquisition->visit;
    $workflow = $visit->visitWorkflow;
    $workflowStep = $workflow->steps()->sole();
    $ticket = $visit->queueTickets()->sole();

    expect($acquisition->status)->toBe(QueueAcquisitionStatus::ACQUIRED);
    expect($visit->status)->toBe(VisitStatus::WAITING);
    expect($workflow->status)->toBe(VisitWorkflowStatus::ACTIVE);
    expect($workflowStep->status)->toBe(VisitWorkflowStepStatus::PENDING);
    expect($ticket->status)->toBe(QueueStatus::CREATED);

    $cancelled = $service->cancel($acquisition);

    expect($cancelled->status)->toBe(QueueAcquisitionStatus::CANCELLED);
    expect($visit->fresh()->status)->toBe(VisitStatus::CANCELLED);
    expect($workflow->fresh()->status)->toBe(VisitWorkflowStatus::CANCELLED);
    expect($workflowStep->fresh()->status)->toBe(VisitWorkflowStepStatus::CANCELLED);
    expect($ticket->fresh()->status)->toBe(QueueStatus::CANCELLED);
    expect(
        QueueEvent::query()
            ->where('queue_ticket_id', $ticket->id)
            ->where('to_status', QueueStatus::CANCELLED->value)
            ->exists()
    )->toBeTrue();

    expect(
        $visit->fresh()->queueTickets()
            ->whereIn('status', [
                QueueStatus::CREATED->value,
                QueueStatus::CALLED->value,
                QueueStatus::IN_PROGRESS->value,
                QueueStatus::ON_HOLD->value,
            ])
            ->exists()
    )->toBeFalse();
});

it('does not allow cancellation after the queue has progressed', function () {
    $fixture = kioskCancellationFixture();

    $service = app(QueueAcquisitionService::class);
    $acquisition = $service->acquire($fixture['department']->code);
    $ticket = $acquisition->visit->queueTickets()->sole();

    $queue = app(QueueService::class);
    $queue->callNext($fixture['station']->id);
    $queue->startTicket($ticket->id);

    expect(fn () => $service->cancel($acquisition))
        ->toThrow(ValidationException::class);

    expect($acquisition->fresh()->status)->toBe(QueueAcquisitionStatus::ACQUIRED);
    expect($acquisition->visit->fresh()->status)->toBe(VisitStatus::IN_PROGRESS);
    expect($ticket->fresh()->status)->toBe(QueueStatus::IN_PROGRESS);
});

it('does not leave a kiosk acquisition registered after cancellation', function () {
    $fixture = kioskCancellationFixture();

    $service = app(QueueAcquisitionService::class);
    $acquisition = $service->acquire($fixture['department']->code);

    $service->cancel($acquisition);

    expect($acquisition->fresh()->status)->toBe(QueueAcquisitionStatus::CANCELLED);
    expect($acquisition->fresh()->visit->intake_channel)->toBe(IntakeChannel::KIOSK);
    expect($acquisition->fresh()->visit->status)->toBe(VisitStatus::CANCELLED);
});
