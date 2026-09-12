<?php

namespace Database\Seeders;

use App\Enums\StationType;
use App\Models\Station;
use Illuminate\Database\Seeder;

class StationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $stations = [
            ['department_id' => 1, 'name' => 'Registration Umum', 'code' => 'REG-U', 'type' => StationType::REGISTRATION->value, 'queue_prefix' => 'A', 'is_active' => true],
            ['department_id' => 1, 'name' => 'Nurse Umum', 'code' => 'NUR-U', 'type' => StationType::NURSE->value, 'queue_prefix' => 'T', 'is_active' => true],
            ['department_id' => 1, 'name' => 'Doctor Umum', 'code' => 'DOC-U', 'type' => StationType::DOCTOR->value, 'queue_prefix' => 'C', 'is_active' => true],
            ['department_id' => 2, 'name' => 'Registration Anak', 'code' => 'REG-A', 'type' => StationType::REGISTRATION->value, 'queue_prefix' => 'A', 'is_active' => true],
            ['department_id' => 2, 'name' => 'Nurse Anak', 'code' => 'NUR-A', 'type' => StationType::NURSE->value, 'queue_prefix' => 'T', 'is_active' => true],
            ['department_id' => 2, 'name' => 'Doctor Anak', 'code' => 'DOC-A', 'type' => StationType::DOCTOR->value, 'queue_prefix' => 'C', 'is_active' => true],
            ['department_id' => 3, 'name' => 'Registration Gigi', 'code' => 'REG-G', 'type' => StationType::REGISTRATION->value, 'queue_prefix' => 'A', 'is_active' => true],
            ['department_id' => 3, 'name' => 'Doctor Gigi', 'code' => 'DOC-G', 'type' => StationType::DOCTOR->value, 'queue_prefix' => 'C', 'is_active' => true],
            ['department_id' => 4, 'name' => 'Registration Mata', 'code' => 'REG-M', 'type' => StationType::REGISTRATION->value, 'queue_prefix' => 'A', 'is_active' => true],
            ['department_id' => 4, 'name' => 'Doctor Mata', 'code' => 'DOC-M', 'type' => StationType::DOCTOR->value, 'queue_prefix' => 'C', 'is_active' => true],
            ['department_id' => 6, 'name' => 'Laboratorium', 'code' => 'LAB', 'type' => StationType::LABORATORY->value, 'queue_prefix' => 'L', 'is_active' => true],
            ['department_id' => 7, 'name' => 'Apotek', 'code' => 'APH', 'type' => StationType::PHARMACY->value, 'queue_prefix' => 'R', 'is_active' => true],
        ];

        foreach ($stations as $station) {
            Station::create($station);
        }
    }
}
