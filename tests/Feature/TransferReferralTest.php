<?php

use App\Enums\IntakeChannel;
use App\Enums\Priority;
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
use App\Services\ReferralService;
use Illuminate\Validation\ValidationException;

function transferReferralFixture(): array
{
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL',
        'is_active' => true,
    ]);

    $sourceStation = Station::create([
        'department_id' => $department->id,
        'name' => 'Doctor 1',
        'code' => 'DOC-01',
        'type' => StationType::DOCTOR,
        'queue_prefix' => 'D1',
        'is_active' => true,
    ]);

    $targetStation = Station::create([
        'department_id' => $department->id,
        'name' => 'Doctor 2',
        'code' => 'DOC-02',
        'type' => StationType::DOCTOR,
        'queue_prefix' => 'D2',
        'is_active' => true,
    ]);

    $unrelatedStation = Station::create([
        'department_id' => $department->id,
        'name' => 'Doctor 3',
        'code' => 'DOC-03',
        'type' => StationType::DOCTOR,
        'queue_prefix' => 'D3',
        'is_active' => true,
    ]);

    $workflow = Workflow::create([
        'department_id' => $department->id,
        'name' => 'General Workflow',
        'is_active' => true,
    ]);

    $version = WorkflowVersion::create([
        'workflow_id' => $workflow->id,
        'version_number' => 1,
        'is_active' => true,
    ]);

    $step = WorkflowStep::create([
        'workflow_version_id' => $version->id,
        'station_id' => $sourceStation->id,
        'name' => 'Doctor Examination',
        'sequence' => 1,
        'requires_queue' => true,
    ]);

    $step->stations()->attach($targetStation->id);

    $patient = Patient::create([
        'name' => 'Patient',
    ]);

    return compact(
        'department',
        'sourceStation',
        'targetStation',
        'unrelatedStation',
        'workflow',
        'version',
        'step',
        'patient',
    );
}

function createTransferReferralVisit(array $fixture): \App\Models\Visit
{
    return app(CreateVisit::class)->handle(
        patientId: $fixture['patient']->id,
        departmentCode: $fixture['department']->code,
        intakeChannel: IntakeChannel::WALK_IN,
    );
}

it('transfers a ticket only to a station allowed by the current workflow step', function () {
    $fixture = transferReferralFixture();
    $visit = createTransferReferralVisit($fixture);
    $ticket = $visit->queueTickets()->sole();

    $result = app(QueueService::class)->transferTicket(
        ticketId: $ticket->id,
        targetStationId: $fixture['targetStation']->id,
    );

    expect($result['original']->status)->toBe(QueueStatus::TRANSFERRED);
    expect($result['original']->transferred_to_station_id)->toBe($fixture['targetStation']->id);
    expect($result['new']->station_id)->toBe($fixture['targetStation']->id);
    expect($result['new']->status)->toBe(QueueStatus::CREATED);
});

it('rejects transfer to a station outside the current workflow step', function () {
    $fixture = transferReferralFixture();
    $visit = createTransferReferralVisit($fixture);
    $ticket = $visit->queueTickets()->sole();

    expect(fn () => app(QueueService::class)->transferTicket(
        ticketId: $ticket->id,
        targetStationId: $fixture['unrelatedStation']->id,
    ))->toThrow(\LogicException::class, 'Transfer target station is not allowed for this workflow step.');

    expect($ticket->fresh()->status)->toBe(QueueStatus::CREATED);
});

it('joins an existing target visit when creating a referral', function () {
    $fixture = transferReferralFixture();
    $sourceVisit = createTransferReferralVisit($fixture);

    $targetDepartment = Department::create([
        'name' => 'Laboratory',
        'code' => 'LAB',
        'is_active' => true,
    ]);

    $targetStation = Station::create([
        'department_id' => $targetDepartment->id,
        'name' => 'Laboratory',
        'code' => 'LAB-01',
        'type' => StationType::LABORATORY,
        'queue_prefix' => 'L',
        'is_active' => true,
    ]);

    $targetWorkflow = Workflow::create([
        'department_id' => $targetDepartment->id,
        'name' => 'Laboratory Workflow',
        'is_active' => true,
    ]);

    $targetVersion = WorkflowVersion::create([
        'workflow_id' => $targetWorkflow->id,
        'version_number' => 1,
        'is_active' => true,
    ]);

    WorkflowStep::create([
        'workflow_version_id' => $targetVersion->id,
        'station_id' => $targetStation->id,
        'name' => 'Laboratory',
        'sequence' => 1,
        'requires_queue' => true,
    ]);

    $targetVisit = app(CreateVisit::class)->handle(
        patientId: $fixture['patient']->id,
        departmentCode: $targetDepartment->code,
        intakeChannel: IntakeChannel::WALK_IN,
        priority: Priority::NORMAL->value,
    );

    $referral = app(ReferralService::class)->create(
        sourceVisitId: $sourceVisit->id,
        targetDepartmentId: $targetDepartment->id,
        priority: Priority::PRIORITY,
    );

    expect($referral->target_visit_id)->toBe($targetVisit->id);
    expect(\App\Models\Visit::query()->where('patient_id', $fixture['patient']->id)->where('department_id', $targetDepartment->id)->count())
        ->toBe(1);
    expect($targetVisit->fresh()->priority)->toBe(Priority::PRIORITY);
    expect($targetVisit->queueTickets()->sole()->priority)->toBe(Priority::PRIORITY);
});

it('rejects duplicate active referrals into the same target visit', function () {
    $fixture = transferReferralFixture();
    $sourceVisit = createTransferReferralVisit($fixture);

    $targetDepartment = Department::create([
        'name' => 'Laboratory',
        'code' => 'LAB',
        'is_active' => true,
    ]);

    $targetStation = Station::create([
        'department_id' => $targetDepartment->id,
        'name' => 'Laboratory',
        'code' => 'LAB-01',
        'type' => StationType::LABORATORY,
        'queue_prefix' => 'L',
        'is_active' => true,
    ]);

    $targetWorkflow = Workflow::create([
        'department_id' => $targetDepartment->id,
        'name' => 'Laboratory Workflow',
        'is_active' => true,
    ]);

    $targetVersion = WorkflowVersion::create([
        'workflow_id' => $targetWorkflow->id,
        'version_number' => 1,
        'is_active' => true,
    ]);

    WorkflowStep::create([
        'workflow_version_id' => $targetVersion->id,
        'station_id' => $targetStation->id,
        'name' => 'Laboratory',
        'sequence' => 1,
        'requires_queue' => true,
    ]);

    $referralService = app(ReferralService::class);

    $referralService->create(
        sourceVisitId: $sourceVisit->id,
        targetDepartmentId: $targetDepartment->id,
    );

    expect(fn () => $referralService->create(
        sourceVisitId: $sourceVisit->id,
        targetDepartmentId: $targetDepartment->id,
    ))->toThrow(ValidationException::class);
});

it('rejects referrals from completed visits', function () {
    $fixture = transferReferralFixture();
    $sourceVisit = createTransferReferralVisit($fixture);
    $sourceVisit->update(['status' => VisitStatus::COMPLETED]);

    $targetDepartment = Department::create([
        'name' => 'Laboratory',
        'code' => 'LAB',
        'is_active' => true,
    ]);

    expect(fn () => app(ReferralService::class)->create(
        sourceVisitId: $sourceVisit->id,
        targetDepartmentId: $targetDepartment->id,
    ))->toThrow(ValidationException::class);
});
