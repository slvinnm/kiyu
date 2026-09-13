<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PatientAuthService
{
    public function register(array $data): array
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => UserRole::PATIENT,
            ]);

            $patient = Patient::create([
                'user_id' => $user->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'national_id' => $data['national_id'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'gender' => $data['gender'] ?? null,
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
            ]);

            return [
                'user' => $user,
                'patient' => $patient,
                'token' => $user->createToken('patient-api')->plainTextToken,
            ];
        });
    }

    public function login(string $email, string $password): array
    {
        $user = User::query()
            ->where('email', $email)
            ->where('role', UserRole::PATIENT->value)
            ->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        $patient = $user->patient;

        if (! $patient) {
            throw ValidationException::withMessages([
                'account' => 'This patient account is not linked to a patient profile.',
            ]);
        }

        return [
            'user' => $user,
            'patient' => $patient,
            'token' => $user->createToken('patient-api')->plainTextToken,
        ];
    }
}
