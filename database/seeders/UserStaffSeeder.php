<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Station;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserStaffSeeder extends Seeder
{
    public function run(): void
    {
        $genDept = Department::where('code', 'GEN')->first();
        $dentalDept = Department::where('code', 'DNT')->first();

        $regStation = Station::where('code', 'GEN-REG')->first();
        $nurseStation = Station::where('code', 'GEN-NUR')->first();
        $doc1Station = Station::where('code', 'GEN-DOC1')->first();
        $doc2Station = Station::where('code', 'GEN-DOC2')->first();
        $phaStation = Station::where('code', 'GEN-PHA')->first();

        // 1. Super Admin
        User::firstOrCreate(
            ['email' => 'admin@kiyu.id'],
            [
                'name' => 'Administrator Kiyu',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        // 2. Receptionist (Alice)
        User::firstOrCreate(
            ['email' => 'reception@kiyu.id'],
            [
                'name' => 'Alice Resepsionis',
                'password' => Hash::make('password'),
                'department_id' => $genDept?->id,
                'station_id' => $regStation?->id,
                'role' => 'receptionist',
            ]
        );

        // 3. Nurse (Bob)
        User::firstOrCreate(
            ['email' => 'nurse@kiyu.id'],
            [
                'name' => 'Bob Perawat',
                'password' => Hash::make('password'),
                'department_id' => $genDept?->id,
                'station_id' => $nurseStation?->id,
                'role' => 'nurse',
            ]
        );

        // 4. Doctor 1 (Dr. Charlie)
        User::firstOrCreate(
            ['email' => 'doctor1@kiyu.id'],
            [
                'name' => 'dr. Charlie Sp.PD',
                'password' => Hash::make('password'),
                'department_id' => $genDept?->id,
                'station_id' => $doc1Station?->id,
                'role' => 'doctor',
            ]
        );

        // 5. Doctor 2 (Dr. Diana)
        User::firstOrCreate(
            ['email' => 'doctor2@kiyu.id'],
            [
                'name' => 'dr. Diana',
                'password' => Hash::make('password'),
                'department_id' => $genDept?->id,
                'station_id' => $doc2Station?->id,
                'role' => 'doctor',
            ]
        );

        // 6. Pharmacist (Edward)
        User::firstOrCreate(
            ['email' => 'pharmacy@kiyu.id'],
            [
                'name' => 'Edward S.Farm, Apt',
                'password' => Hash::make('password'),
                'department_id' => $genDept?->id,
                'station_id' => $phaStation?->id,
                'role' => 'pharmacist',
            ]
        );
    }
}
