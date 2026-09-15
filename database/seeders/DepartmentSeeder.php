<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create departments/policlinics
        $departments = [
            ['name' => 'Poliklinik Umum', 'code' => 'POLIUM', 'is_active' => true], // 1
            ['name' => 'Poliklinik Anak', 'code' => 'POLIA', 'is_active' => true], // 2
            ['name' => 'Poliklinik Gigi', 'code' => 'POLIGI', 'is_active' => true], // 3
            ['name' => 'Poliklinik Mata', 'code' => 'POLIM', 'is_active' => true], // 4
            ['name' => 'Poliklinik Kandungan', 'code' => 'POLKB', 'is_active' => true], // 5
            ['name' => 'Laboratorium Klinik', 'code' => 'LAB', 'is_active' => true], // 6
            ['name' => 'Apotek', 'code' => 'APOTEK', 'is_active' => true], // 7
            ['name' => 'Radiologi', 'code' => 'RAD', 'is_active' => true], // 8
        ];

        foreach ($departments as $dept) {
            Department::create($dept);
        }
    }
}
