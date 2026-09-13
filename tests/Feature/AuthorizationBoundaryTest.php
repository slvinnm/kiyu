<?php

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\QueueTicket;
use App\Models\Referral;
use App\Models\Station;
use App\Models\User;
use App\Models\Visit;
use App\Policies\QueueTicketPolicy;
use App\Policies\ReferralPolicy;
use App\Policies\StationPolicy;
use App\Policies\VisitPolicy;

it('denies patient access to internal station endpoints', function (): void {
    $patient = User::factory()->create([
        'role' => UserRole::PATIENT,
    ]);

    $this->actingAs($patient, 'sanctum')
        ->getJson('/api/v1/queue/stations')
        ->assertForbidden();
});

it('denies staff access to the patient online visit endpoint', function (): void {
    $staff = User::factory()->create([
        'role' => UserRole::DOCTOR,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->postJson('/api/v1/online/visits', [
            'department_code' => 'GENERAL',
        ])
        ->assertForbidden();
});

it('denies staff access to the patient profile endpoint', function (): void {
    $staff = User::factory()->create([
        'role' => UserRole::DOCTOR,
    ]);

    $this->actingAs($staff, 'sanctum')
        ->getJson('/api/v1/patient/me')
        ->assertForbidden();
});

it('keeps queue ticket authorization scoped to operational staff and station', function (): void {
    $policy = new QueueTicketPolicy;
    $ticket = new QueueTicket(['station_id' => 10]);

    $patient = new User(['role' => UserRole::PATIENT]);
    $doctor = new User([
        'role' => UserRole::DOCTOR,
        'station_id' => 10,
    ]);
    $otherDoctor = new User([
        'role' => UserRole::DOCTOR,
        'station_id' => 11,
    ]);

    expect($policy->manage($patient, $ticket))->toBeFalse();
    expect($policy->manage($doctor, $ticket))->toBeTrue();
    expect($policy->manage($otherDoctor, $ticket))->toBeFalse();
});

it('keeps station authorization scoped to operational staff and station', function (): void {
    $policy = new StationPolicy;
    $station = new Station;
    $station->setAttribute('id', 10);

    $patient = new User(['role' => UserRole::PATIENT]);
    $staff = new User([
        'role' => UserRole::NURSE,
        'station_id' => 10,
    ]);
    $otherStaff = new User([
        'role' => UserRole::NURSE,
        'station_id' => 11,
    ]);
    $admin = new User(['role' => UserRole::ADMIN]);

    expect($policy->view($patient, $station))->toBeFalse();
    expect($policy->view($staff, $station))->toBeTrue();
    expect($policy->view($otherStaff, $station))->toBeFalse();
    expect($policy->view($admin, $station))->toBeTrue();
});

it('keeps patient visit authorization owned by the authenticated patient', function (): void {
    $policy = new VisitPolicy;
    $patient = new Patient;
    $patient->setAttribute('id', 10);
    $visit = new Visit(['patient_id' => 10]);
    $otherVisit = new Visit(['patient_id' => 11]);

    $user = new User(['role' => UserRole::PATIENT]);
    $user->setRelation('patient', $patient);

    expect($policy->view($user, $visit))->toBeTrue();
    expect($policy->view($user, $otherVisit))->toBeFalse();
});

it('keeps referral viewing restricted to the related departments', function (): void {
    $policy = new ReferralPolicy;
    $sourceVisit = new Visit(['department_id' => 10]);
    $referral = new Referral([
        'target_department_id' => 20,
    ]);
    $referral->setRelation('sourceVisit', $sourceVisit);

    $patient = new User([
        'role' => UserRole::PATIENT,
        'department_id' => 10,
    ]);
    $doctor = new User([
        'role' => UserRole::DOCTOR,
        'department_id' => 10,
    ]);
    $targetDoctor = new User([
        'role' => UserRole::DOCTOR,
        'department_id' => 20,
    ]);
    $otherDoctor = new User([
        'role' => UserRole::DOCTOR,
        'department_id' => 30,
    ]);

    expect($policy->view($patient, $referral))->toBeFalse();
    expect($policy->view($doctor, $referral))->toBeTrue();
    expect($policy->view($targetDoctor, $referral))->toBeTrue();
    expect($policy->view($otherDoctor, $referral))->toBeFalse();
});

it('requires an internal clinical role for changing visit priority', function (): void {
    $policy = new VisitPolicy;
    $visit = new Visit(['department_id' => 10]);

    $patient = new User([
        'role' => UserRole::PATIENT,
        'department_id' => 10,
    ]);
    $doctor = new User([
        'role' => UserRole::DOCTOR,
        'department_id' => 10,
    ]);
    $otherDoctor = new User([
        'role' => UserRole::DOCTOR,
        'department_id' => 20,
    ]);
    $admin = new User(['role' => UserRole::ADMIN]);

    expect($policy->setPriority($patient, $visit))->toBeFalse();
    expect($policy->setPriority($doctor, $visit))->toBeTrue();
    expect($policy->setPriority($otherDoctor, $visit))->toBeFalse();
    expect($policy->setPriority($admin, $visit))->toBeTrue();
});
