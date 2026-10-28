<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    public function run(): void
    {
        // Deterministic known patients for manual frontend testing
        Patient::firstOrCreate(
            ['mrn' => 'MRN-10001'],
            [
                'nik' => '3201010101900001',
                'name' => 'Budi Santoso',
                'date_of_birth' => '1990-01-01',
                'gender' => 'male',
                'phone' => '081234567890',
                'email' => 'budi.santoso@example.com',
                'address' => 'Jl. Sudirman No. 45, Jakarta Pusat',
            ]
        );

        Patient::firstOrCreate(
            ['mrn' => 'MRN-10002'],
            [
                'nik' => '3201010202920002',
                'name' => 'Siti Aminah',
                'date_of_birth' => '1992-02-02',
                'gender' => 'female',
                'phone' => '081234567891',
                'email' => 'siti.aminah@example.com',
                'address' => 'Jl. Gatot Subroto No. 12, Jakarta Selatan',
            ]
        );

        Patient::firstOrCreate(
            ['mrn' => 'MRN-10003'],
            [
                'nik' => '3201010303850003',
                'name' => 'Joko Prasetyo',
                'date_of_birth' => '1985-03-15',
                'gender' => 'male',
                'phone' => '081234567892',
                'email' => 'joko.prasetyo@example.com',
                'address' => 'Jl. Asia Afrika No. 88, Bandung',
            ]
        );

        // Additional 20 random patients using Factory with Faker
        Patient::factory()->count(20)->create();
    }
}
