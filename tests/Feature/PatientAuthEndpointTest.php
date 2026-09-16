<?php

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Tests\Support\ApiScenario;

it('registers a patient account and returns an api token', function (): void {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'New Patient',
        'email' => 'new-patient@example.test',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'gender' => 'female',
    ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.name', 'New Patient')
        ->assertJsonPath('data.user.role', UserRole::PATIENT->value)
        ->assertJsonPath('data.user.profile.name', 'New Patient')
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'profile',
                ],
            ],
        ]);

    $user = User::query()
        ->where('email', 'new-patient@example.test')
        ->firstOrFail();

    expect($user->role)->toBe(UserRole::PATIENT);
    expect(
        Patient::query()
            ->where('user_id', $user->id)
            ->exists()
    )->toBeTrue();

    expect($user->tokens()->count())->toBe(1);
});

it('rejects an invalid registration payload with validation errors', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'name' => '',
        'email' => 'invalid-email',
        'password' => 'short',
        'password_confirmation' => 'different',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'email',
            'password',
        ]);
});

it('logs a patient in with valid credentials', function (): void {
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

    $this->postJson('/api/v1/auth/login', [
        'email' => 'patient@example.test',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'patient@example.test')
        ->assertJsonPath('data.user.role', UserRole::PATIENT->value)
        ->assertJsonPath('data.user.profile.email', 'patient@example.test')
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'profile',
                ],
            ],
        ]);

    expect($user->tokens()->count())->toBe(1);
});

it('logs an admin in through the same auth endpoint', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::ADMIN,
        'email' => 'admin@example.test',
        'password' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'admin@example.test',
        'password' => 'password123',
    ])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.user.email', 'admin@example.test')
        ->assertJsonPath('data.user.role', UserRole::ADMIN->value)
        ->assertJsonMissingPath('data.user.profile')
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => [
                    'id',
                    'name',
                    'email',
                    'role',
                ],
            ],
        ]);

    expect($user->tokens()->count())->toBe(1);
});

it('rejects invalid login credentials', function (): void {
    User::factory()->create([
        'role' => UserRole::PATIENT,
        'email' => 'patient@example.test',
        'password' => 'password123',
    ]);

    $this->postJson('/api/v1/auth/login', [
        'email' => 'patient@example.test',
        'password' => 'wrong-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'email',
        ]);
});

it('returns the authenticated user profile', function (): void {
    $account = ApiScenario::patientAccount();

    $this->actingAs($account['user'], 'sanctum')
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $account['user']->id)
        ->assertJsonPath('data.role', UserRole::PATIENT->value)
        ->assertJsonPath('data.profile.id', $account['patient']->id)
        ->assertJsonStructure([
            'data' => [
                'id',
                'name',
                'email',
                'role',
                'profile',
            ],
        ]);
});

it('rejects unauthenticated access to the authenticated user profile', function (): void {
    $this->getJson('/api/v1/auth/me')
        ->assertUnauthorized();
});

it('logs the authenticated user out and revokes the current token', function (): void {
    $account = ApiScenario::patientAccount();

    $token = $account['user']
        ->createToken('test')
        ->plainTextToken;

    $this->withToken($token)
        ->postJson('/api/v1/auth/logout')
        ->assertOk()
        ->assertJson([
            'success' => true,
            'message' => 'Logout successful.',
        ]);

    expect($account['user']->tokens()->count())->toBe(0);
});
