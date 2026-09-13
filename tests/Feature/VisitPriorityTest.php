<?php

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Services\UpdateVisitPriority;

function priorityFixture(): array
{
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL',
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

    $visit = Visit::create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'workflow_version_id' => $version->id,
        'visit_number' => 'V-TEST-00001',
        'priority' => Priority::NORMAL->value,
        'intake_channel' => IntakeChannel::WALK_IN->value,
        'status' => VisitStatus::WAITING->value,
    ]);

    return compact('department', 'patient', 'visit');
}

it('allows authorized clinical staff to change visit priority and audits the change', function () {
    $fixture = priorityFixture();

    $user = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $fixture['department']->id,
    ]);

    $updated = app(UpdateVisitPriority::class)->handle(
        visit: $fixture['visit'],
        priority: Priority::EMERGENCY,
        user: $user,
    );

    expect($updated->priority)->toBe(Priority::EMERGENCY);
    expect($fixture['visit']->fresh()->priority)->toBe(Priority::EMERGENCY);
    expect(AuditLog::query()
        ->where('action', 'VISIT_PRIORITY_CHANGED')
        ->where('auditable_id', $fixture['visit']->id)
        ->exists())->toBeTrue();
});

it('rejects staff from another department', function () {
    $fixture = priorityFixture();

    $otherDepartment = Department::create([
        'name' => 'Dental',
        'code' => 'DENTAL',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'role' => UserRole::NURSE,
        'department_id' => $otherDepartment->id,
    ]);

    expect(fn () => app(UpdateVisitPriority::class)->handle(
        visit: $fixture['visit'],
        priority: Priority::PRIORITY,
        user: $user,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});
