<?php

use App\Enums\IntakeChannel;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\QueueService;
use Tests\Support\ApiScenario;

it('creates an online visit and prevents duplicate active visits', function (): void {
    $department = ApiScenario::department('ONLINE-VISIT');
    $station = ApiScenario::station($department, 'ONLINE-REG');
    ApiScenario::workflow($department, [$station]);
    $account = ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->postJson('/api/v1/online/visits', ['department_code' => 'ONLINE-VISIT'])
        ->assertCreated()
        ->assertJsonPath('data.intake_channel', IntakeChannel::ONLINE->value);

    $this->postJson('/api/v1/online/visits', ['department_code' => 'ONLINE-VISIT'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['department_code']);
});

it('does not call an online ticket before physical check-in', function (): void {
    $department = ApiScenario::department('ONLINE-GATING');
    $station = ApiScenario::station($department, 'ONLINE-GATING-REG');
    ApiScenario::workflow($department, [$station]);
    $account = ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->postJson('/api/v1/online/visits', ['department_code' => $department->code])
        ->assertCreated();

    expect(app(QueueService::class)->callNext($station->id))->toBeNull();
});

it('lists and returns only the authenticated patient visits', function (): void {
    $department = ApiScenario::department('PATIENT-VISITS');
    $station = ApiScenario::station($department, 'PATIENT-REG');
    ApiScenario::workflow($department, [$station]);
    $account = ApiScenario::patientAccount();
    $other = ApiScenario::patientAccount(['email' => 'other-patient@example.test']);
    $visit = ApiScenario::visit($account['patient'], $department);
    $otherVisit = ApiScenario::visit($other['patient'], $department);

    $this->actingAs($account['user'], 'sanctum')
        ->getJson('/api/v1/patient/visits')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visit->id);

    $this->getJson('/api/v1/patient/visits/' . $visit->id)
        ->assertOk()
        ->assertJsonPath('data.id', $visit->id);

    $this->getJson('/api/v1/patient/visits/' . $otherVisit->id)->assertForbidden();
});

it('returns active queue tickets and checks in an online visit', function (): void {
    $department = ApiScenario::department('CHECKIN-VISIT');
    $station = ApiScenario::station($department, 'CHECKIN-REG');
    ApiScenario::workflow($department, [$station]);
    $account = ApiScenario::patientAccount();
    $onlineVisit = ApiScenario::visit($account['patient'], $department, IntakeChannel::ONLINE);

    $this->actingAs($account['user'], 'sanctum')
        ->getJson('/api/v1/patient/queue')
        ->assertOk()
        ->assertJsonPath('data.0.visit.id', $onlineVisit->id);

    $this->postJson('/api/v1/patient/visits/' . $onlineVisit->id . '/check-in')
        ->assertOk()
        ->assertJsonPath('data.status', 'WAITING');

    expect($onlineVisit->fresh()->status->value)->toBe('WAITING');
});

it('rejects patient-only endpoints for staff users', function (): void {
    $staff = User::factory()->create(['role' => UserRole::DOCTOR]);

    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/v1/patient/visits')
        ->assertForbidden();
});
