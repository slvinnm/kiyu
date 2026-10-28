<?php

namespace Database\Seeders;

use App\Enums\StationType;
use App\Models\Department;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Department / Poli
        $general = Department::firstOrCreate([
            'code' => 'GEN',
        ], [
            'name' => 'Poli Umum',
            'is_active' => true,
        ]);

        $dental = Department::firstOrCreate([
            'code' => 'DNT',
        ], [
            'name' => 'Poli Gigi',
            'is_active' => true,
        ]);

        // 2. Station / Pos Pelayanan per Department
        // General Clinic
        Station::firstOrCreate([
            'code' => 'GEN-REG',
        ], [
            'department_id' => $general->id,
            'name' => 'Loket Pendaftaran Umum',
            'type' => StationType::Registration,
            'queue_prefix' => 'R',
            'is_active' => true,
        ]);

        Station::firstOrCreate([
            'code' => 'GEN-NUR',
        ], [
            'department_id' => $general->id,
            'name' => 'Ruang Perawat Umum',
            'type' => StationType::Nurse,
            'queue_prefix' => 'N',
            'is_active' => true,
        ]);

        Station::firstOrCreate([
            'code' => 'GEN-DOC1',
        ], [
            'department_id' => $general->id,
            'name' => 'Ruang Dokter 1',
            'type' => StationType::Doctor,
            'queue_prefix' => 'D1',
            'is_active' => true,
        ]);

        Station::firstOrCreate([
            'code' => 'GEN-DOC2',
        ], [
            'department_id' => $general->id,
            'name' => 'Ruang Dokter 2',
            'type' => StationType::Doctor,
            'queue_prefix' => 'D2',
            'is_active' => true,
        ]);

        Station::firstOrCreate([
            'code' => 'GEN-PHA',
        ], [
            'department_id' => $general->id,
            'name' => 'Apotek',
            'type' => StationType::Pharmacy,
            'queue_prefix' => 'P',
            'is_active' => true,
        ]);

        // Dental Clinic
        Station::firstOrCreate([
            'code' => 'DNT-REG',
        ], [
            'department_id' => $dental->id,
            'name' => 'Loket Pendaftaran Gigi',
            'type' => StationType::Registration,
            'queue_prefix' => 'RG',
            'is_active' => true,
        ]);

        Station::firstOrCreate([
            'code' => 'DNT-DOC',
        ], [
            'department_id' => $dental->id,
            'name' => 'Ruang Dokter Gigi',
            'type' => StationType::Doctor,
            'queue_prefix' => 'DG',
            'is_active' => true,
        ]);

        // 3. Admin / User (untuk otentikasi staf)
        User::firstOrCreate([
            'email' => 'admin@kiyu.id',
        ], [
            'name' => 'Admin Kiyu',
            'password' => bcrypt('password'),
        ]);

        User::firstOrCreate([
            'email' => 'reception@kiyu.id',
        ], [
            'name' => 'Resepsionis Kiyu',
            'password' => bcrypt('password'),
        ]);
    }
}
