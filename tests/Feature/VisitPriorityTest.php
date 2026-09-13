<?php

use App\Enums\Priority;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use App\Services\UpdateVisitPriority;

it('allows authorized clinical staff to change visit priority and audits the change', function () {
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL',
        'is_active' => true,
    ]);

    $patient = Patient::create([
        'name' => 'Patient',
    ]);

    $visit = Visit::create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'workflow_version_id' => 1,
        'visit_number' => 'V-TEST-00001',
        'priority' => Priority::NORMAL->value,
        'intake_channel' => 'WALK_IN',
        'status' => 'WAITING',
    ]);

    $user = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $department->id,
    ]);

    $updated = app(UpdateVisitPriority::class)->handle(
        visit: $visit,
        priority: Priority::EMERGENCY,
        user: $user,
    );

    expect($updated->priority)->toBe(Priority::EMERGENCY);
    expect($visit->fresh()->priority)->toBe(Priority::EMERGENCY);
    expect($updated->auditLogs)->toBeNull();

    expect(\App\Models\AuditLog::query()
        ->where('action', 'VISIT_PRIORITY_CHANGED')
        ->where('auditable_id', $visit->id)
        ->exists())->toBeTrue();
});

it('rejects staff from another department', function () {
    $department = Department::create([
        'name' => 'General',
        'code' => 'GENERAL',
        'is_active' => true,
    ]);

    $otherDepartment = Department::create([
        'name' => 'Dental',
        'code' => 'DENTAL',
        'is_active' => true,
    ]);

    $patient = Patient::create(['name' => 'Patient']);

    $visit = Visit::create([
        'patient_id' => $patient->id,
        'department_id' => $department->id,
        'workflow_version_id' => 1,
        'visit_number' => 'V-TEST-00002',
        'priority' => Priority::NORMAL->value,
        'intake_channel' => 'WALK_IN',
        'status' => 'WAITING',
    ]);

    $user = User::factory()->create([
        'role' => UserRole::NURSE,
        'department_id' => $otherDepartment->id,
    ]);

    expect(fn () => app(UpdateVisitPriority::class)->handle(
        visit: $visit,
        priority: Priority::PRIORITY,
        user: $user,
    ))->toThrow(\Illuminate\Validation\ValidationException::class);
});
