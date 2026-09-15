<?php

use App\Enums\Priority;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Patient;
use App\Models\User;
use Tests\Support\ApiScenario;

it('updates visit priority for authorized clinical staff and persists the audit', function (): void {
    $department = ApiScenario::department('PRIORITY-ENDPOINT');
    $station = ApiScenario::station($department, 'PRIORITY-DOC', StationType::DOCTOR, 'D');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Priority Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $doctor = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $department->id,
        'station_id' => $station->id,
    ]);

    $this->actingAs($doctor, 'sanctum')
        ->patchJson('/api/v1/priority/visits/' . $visit->id, [
            'priority' => Priority::EMERGENCY->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.priority', Priority::EMERGENCY->value);

    expect($visit->fresh()->priority)->toBe(Priority::EMERGENCY);
    expect(AuditLog::query()->where('action', 'VISIT_PRIORITY_CHANGED')->where('auditable_id', $visit->id)->exists())
        ->toBeTrue();
});

it('rejects invalid priority values at the endpoint', function (): void {
    $department = ApiScenario::department('PRIORITY-VALIDATION');
    $station = ApiScenario::station($department, 'PRIORITY-VALIDATION-DOC', StationType::DOCTOR, 'D');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Priority Validation Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $doctor = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $department->id,
    ]);

    $this->actingAs($doctor, 'sanctum')
        ->patchJson('/api/v1/priority/visits/' . $visit->id, ['priority' => 99])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['priority']);
});

it('forbids clinical staff from another department from changing priority', function (): void {
    $department = ApiScenario::department('PRIORITY-BOUNDARY');
    $station = ApiScenario::station($department, 'PRIORITY-BOUNDARY-DOC', StationType::DOCTOR, 'D');
    ApiScenario::workflow($department, [$station]);
    $patient = Patient::create(['name' => 'Priority Boundary Patient']);
    $visit = ApiScenario::visit($patient, $department);
    $otherDepartment = ApiScenario::department('PRIORITY-OTHER');
    $doctor = User::factory()->create([
        'role' => UserRole::DOCTOR,
        'department_id' => $otherDepartment->id,
    ]);

    $this->actingAs($doctor, 'sanctum')
        ->patchJson('/api/v1/priority/visits/' . $visit->id, [
            'priority' => Priority::PRIORITY->value,
        ])->assertForbidden();
});
