<?php

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Tests\Support\ApiScenario;

it('registers a patient account and returns an api token', function (): void {
    $response = $this->postJson('/api/v1/patient/register', [
        'name' => 'New Patient',
        'email' => 'new-patient@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'gender' => 'female',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.patient.name', 'New Patient')
        ->assertJsonStructure(['data' => ['token', 'patient']]);

    $user = User::query()->where('email', 'new-patient@example.test')->firstOrFail();
    expect($user->role)->toBe(UserRole::PATIENT);
    expect(Patient::query()->where('user_id', $user->id)->exists())->toBeTrue();
    expect($user->tokens()->count())->toBe(1);
});

it('rejects an invalid patient registration payload with validation errors', function (): void {
    $this->postJson('/api/v1/patient/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'password']);
});

it('logs a patient in and rejects invalid credentials', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::PATIENT,
        'email' => 'patient@example.test',
        'password' => 'password123',
    ]);
    Patient::create([
        'user_id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);

    $this->postJson('/api/v1/patient/login', [
        'email' => 'patient@example.test',
        'password' => 'password123',
    ])->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['token', 'patient']]);

    $this->postJson('/api/v1/patient/login', [
        'email' => 'patient@example.test',
        'password' => 'wrong-password',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns the authenticated patient profile and rejects unauthenticated access', function (): void {
    $account = ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->getJson('/api/v1/patient/me')
        ->assertOk()
        ->assertJsonPath('data.id', $account['patient']->id);

    app('auth')->forgetGuards();

    $this->getJson('/api/v1/patient/me')->assertUnauthorized();
});
