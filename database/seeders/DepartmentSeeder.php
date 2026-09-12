<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create departments/policlinics
        $departments = [
            ['name' => 'Poliklinik Umum', 'code' => 'POLIUM', 'is_active' => true],
            ['name' => 'Poliklinik Anak', 'code' => 'POLIA', 'is_active' => true],
            ['name' => 'Poliklinik Gigi', 'code' => 'POLIGI', 'is_active' => true],
            ['name' => 'Poliklinik Mata', 'code' => 'POLIM', 'is_active' => true],
            ['name' => 'Poliklinik Kandungan', 'code' => 'POLKB', 'is_active' => true],
            ['name' => 'Laboratorium Klinik', 'code' => 'LAB', 'is_active' => true],
            ['name' => 'Apotek', 'code' => 'APOTEK', 'is_active' => true],
            ['name' => 'Radiologi', 'code' => 'RAD', 'is_active' => true],
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }
    }
}