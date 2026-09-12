<?php

namespace Database\Seeders;

use App\Models\Patient;
use Illuminate\Database\Seeder;

class PatientSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create sample patients
        $patients = [
            ['name' => 'Budi Santoso', 'national_id' => '3273010101900001', 'phone' => '081234567890'],
            ['name' => 'Siti Nurhaliza', 'national_id' => '3273010202910002', 'phone' => '081234567891'],
            ['name' => 'Ahmad Fauzi', 'national_id' => '3273010303850003', 'phone' => '081234567892'],
            ['name' => 'Dewi Lestari', 'national_id' => '3273010404920004', 'phone' => '081234567893'],
            ['name' => 'Rizki Ramadhan', 'national_id' => '3273010505880005', 'phone' => '081234567894'],
            ['name' => 'Mega Putri', 'national_id' => '3273010606950006', 'phone' => '081234567895'],
            ['name' => 'Adi Pratama', 'national_id' => '3273010707890007', 'phone' => '081234567896'],
            ['name' => 'Lina Marlina', 'national_id' => '3273010808900008', 'phone' => '081234567897'],
        ];

        foreach ($patients as $patientData) {
            Patient::create($patientData);
        }
    }
}
