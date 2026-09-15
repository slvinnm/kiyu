<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Look up departments by code (not hard-coded IDs)
        $deptUmum = Department::where('code', 'POLIUM')->firstOrFail();
        $deptAnak = Department::where('code', 'POLIA')->firstOrFail();
        $deptGigi = Department::where('code', 'POLIGI')->firstOrFail();
        $deptMata = Department::where('code', 'POLIM')->firstOrFail();

        // Look up stations by code (not hard-coded IDs)
        $stationRegUmum = Station::where('code', 'REG-U')->firstOrFail();
        $stationNurUmum = Station::where('code', 'NUR-U')->firstOrFail();
        $stationDocUmum = Station::where('code', 'DOC-U')->firstOrFail();
        $stationRegAnak = Station::where('code', 'REG-A')->firstOrFail();
        $stationNurAnak = Station::where('code', 'NUR-A')->firstOrFail();
        $stationDocAnak = Station::where('code', 'DOC-A')->firstOrFail();
        $stationRegGigi = Station::where('code', 'REG-G')->firstOrFail();
        $stationDocGigi = Station::where('code', 'DOC-G')->firstOrFail();
        $stationRegMata = Station::where('code', 'REG-M')->firstOrFail();
        $stationDocMata = Station::where('code', 'DOC-M')->firstOrFail();

        // Create workflow for Poli Umum
        $workUmum = Workflow::create([
            'department_id' => $deptUmum->id,
            'name' => 'Poli Umum Workflow',
            'is_active' => true,
        ]);
        $verUmum = WorkflowVersion::create([
            'workflow_id' => $workUmum->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $stepsUmum = [
            ['workflow_version_id' => $verUmum->id, 'station_id' => $stationRegUmum->id, 'name' => 'Registration', 'sequence' => 1, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verUmum->id, 'station_id' => $stationNurUmum->id, 'name' => 'Nurse Assessment', 'sequence' => 2, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verUmum->id, 'station_id' => $stationDocUmum->id, 'name' => 'Doctor Consultation', 'sequence' => 3, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
        ];
        foreach ($stepsUmum as $step) {
            WorkflowStep::create($step);
        }

        // Create workflow for Poli Anak
        $workAnak = Workflow::create([
            'department_id' => $deptAnak->id,
            'name' => 'Poli Anak Workflow',
            'is_active' => true,
        ]);
        $verAnak = WorkflowVersion::create([
            'workflow_id' => $workAnak->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $stepsAnak = [
            ['workflow_version_id' => $verAnak->id, 'station_id' => $stationRegAnak->id, 'name' => 'Registration', 'sequence' => 1, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verAnak->id, 'station_id' => $stationNurAnak->id, 'name' => 'Nurse Assessment', 'sequence' => 2, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verAnak->id, 'station_id' => $stationDocAnak->id, 'name' => 'Doctor Consultation', 'sequence' => 3, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
        ];
        foreach ($stepsAnak as $step) {
            WorkflowStep::create($step);
        }

        // Create workflow for Poli Gigi
        $workGigi = Workflow::create([
            'department_id' => $deptGigi->id,
            'name' => 'Poli Gigi Workflow',
            'is_active' => true,
        ]);
        $verGigi = WorkflowVersion::create([
            'workflow_id' => $workGigi->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $stepsGigi = [
            ['workflow_version_id' => $verGigi->id, 'station_id' => $stationRegGigi->id, 'name' => 'Registration', 'sequence' => 1, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verGigi->id, 'station_id' => $stationDocGigi->id, 'name' => 'Doctor Consultation', 'sequence' => 2, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
        ];
        foreach ($stepsGigi as $step) {
            WorkflowStep::create($step);
        }

        // Create workflow for Poli Mata
        $workMata = Workflow::create([
            'department_id' => $deptMata->id,
            'name' => 'Poli Mata Workflow',
            'is_active' => true,
        ]);
        $verMata = WorkflowVersion::create([
            'workflow_id' => $workMata->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);
        $stepsMata = [
            ['workflow_version_id' => $verMata->id, 'station_id' => $stationRegMata->id, 'name' => 'Registration', 'sequence' => 1, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
            ['workflow_version_id' => $verMata->id, 'station_id' => $stationDocMata->id, 'name' => 'Doctor Consultation', 'sequence' => 2, 'requires_queue' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false],
        ];
        foreach ($stepsMata as $step) {
            WorkflowStep::create($step);
        }
    }
}
