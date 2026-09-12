<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create users with roles
        $users = [
            [
                'name' => 'Admin User',
                'email' => 'admin@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::ADMIN->value,
                'department_id' => null,
                'station_id' => null,
            ],
            [
                'name' => 'Receptionist',
                'email' => 'reception@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::RECEPTIONIST->value,
                'department_id' => null,
                'station_id' => null,
            ],
            [
                'name' => 'Nurse Umum',
                'email' => 'nurse.umum@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::NURSE->value,
                'department_id' => 1, // Poli Umum
                'station_id' => 1,  // Registration
            ],
            [
                'name' => 'Doctor Umum',
                'email' => 'doctor.umum@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::DOCTOR->value,
                'department_id' => 1, // Poli Umum
                'station_id' => 2,  // Doctor
            ],
            [
                'name' => 'Nurse Anak',
                'email' => 'nurse.anak@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::NURSE->value,
                'department_id' => 2, // Poli Anak
                'station_id' => 3,  // Registration
            ],
            [
                'name' => 'Doctor Anak',
                'email' => 'doctor.anak@kiyu.test',
                'password' => Hash::make('password'),
                'role' => UserRole::DOCTOR->value,
                'department_id' => 2, // Poli Anak
                'station_id' => 4,  // Doctor
            ],
        ];

        foreach ($users as $userData) {
            User::create($userData);
        }
    }
}
