<?php

namespace Tests\Support;

use App\Enums\IntakeChannel;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Station;
use App\Models\User;
use App\Models\Visit;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use App\Services\CreateVisit;

final class ApiScenario
{
  public static function department(string $code = 'GENERAL-API', bool $active = true): Department
  {
    return Department::create([
      'name' => str($code)->replace('-', ' ')->title()->toString(),
      'code' => $code,
      'is_active' => $active,
    ]);
  }

  public static function station(
    Department $department,
    string $code = 'REG-API',
    StationType $type = StationType::REGISTRATION,
    string $prefix = 'A',
    bool $active = true,
  ): Station {
    return Station::create([
      'department_id' => $department->id,
      'name' => $code,
      'code' => $code,
      'type' => $type,
      'queue_prefix' => $prefix,
      'is_active' => $active,
    ]);
  }

  /**
   * @param  array<int, Station>  $stations
   */
  public static function workflow(Department $department, array $stations): WorkflowVersion
  {
    $workflow = Workflow::create([
      'department_id' => $department->id,
      'name' => 'API Workflow',
      'is_active' => true,
    ]);

    $version = WorkflowVersion::create([
      'workflow_id' => $workflow->id,
      'version_number' => 1,
      'is_active' => true,
    ]);

    foreach ($stations as $sequence => $station) {
      WorkflowStep::create([
        'workflow_version_id' => $version->id,
        'station_id' => $station->id,
        'name' => $station->name,
        'sequence' => $sequence + 1,
        'requires_queue' => true,
      ]);
    }

    return $version;
  }

  /** @return array{user: User, patient: Patient} */
  public static function patientAccount(array $attributes = []): array
  {
    $user = User::factory()->create(array_merge([
      'role' => UserRole::PATIENT,
    ], $attributes));

    $patient = Patient::create([
      'user_id' => $user->id,
      'name' => $user->name,
      'email' => $user->email,
    ]);

    return compact('user', 'patient');
  }

  public static function visit(Patient $patient, Department $department, IntakeChannel $channel = IntakeChannel::WALK_IN): Visit
  {
    return app(CreateVisit::class)->handle(
      patientId: $patient->id,
      departmentCode: $department->code,
      intakeChannel: $channel,
    );
  }
}
