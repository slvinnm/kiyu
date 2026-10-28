<?php

namespace Database\Seeders;

use App\Enums\StationType;
use App\Models\Department;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $general = Department::firstOrCreate(
            ['code' => 'GEN'],
            ['name' => 'Poli Umum', 'is_active' => true]
        );

        $dental = Department::firstOrCreate(
            ['code' => 'DNT'],
            ['name' => 'Poli Gigi', 'is_active' => true]
        );

        $cardio = Department::firstOrCreate(
            ['code' => 'CAR'],
            ['name' => 'Jantung', 'is_active' => true]
        );

        $registrasiGeneral = Station::firstOrCreate(
            ['code' => 'GEN-REG'],
            [
                'department_id' => $general->id,
                'name' => 'Loket Pendaftaran Umum',
                'type' => StationType::Registration,
                'queue_prefix' => 'R',
                'is_active' => true,
            ]
        );

        $nurseGeneral = Station::firstOrCreate(
            ['code' => 'GEN-NUR'],
            [
                'department_id' => $general->id,
                'name' => 'Ruang Perawat Umum',
                'type' => StationType::Nurse,
                'queue_prefix' => 'N',
                'is_active' => true,
            ]
        );

        $doctor1 = Station::firstOrCreate(
            ['code' => 'GEN-DOC1'],
            [
                'department_id' => $general->id,
                'name' => 'Ruang Dokter 1',
                'type' => StationType::Doctor,
                'queue_prefix' => 'D1',
                'is_active' => true,
            ]
        );

        $doctor2 = Station::firstOrCreate(
            ['code' => 'GEN-DOC2'],
            [
                'department_id' => $general->id,
                'name' => 'Ruang Dokter 2',
                'type' => StationType::Doctor,
                'queue_prefix' => 'D2',
                'is_active' => true,
            ]
        );

        $pharmacy = Station::firstOrCreate(
            ['code' => 'GEN-PHA'],
            [
                'department_id' => $general->id,
                'name' => 'Apotek',
                'type' => StationType::Pharmacy,
                'queue_prefix' => 'P',
                'is_active' => true,
            ]
        );

        // Dental
        Station::firstOrCreate(
            ['code' => 'DNT-REG'],
            [
                'department_id' => $dental->id,
                'name' => 'Loket Pendaftaran Gigi',
                'type' => StationType::Registration,
                'queue_prefix' => 'RG',
                'is_active' => true,
            ]
        );

        Station::firstOrCreate(
            ['code' => 'DNT-DOC'],
            [
                'department_id' => $dental->id,
                'name' => 'Ruang Dokter Gigi',
                'type' => StationType::Doctor,
                'queue_prefix' => 'DG',
                'is_active' => true,
            ]
        );

        // Cardio
        Station::firstOrCreate(
            ['code' => 'CAR-REG'],
            [
                'department_id' => $cardio->id,
                'name' => 'Loket Pendaftaran Jantung',
                'type' => StationType::Registration,
                'queue_prefix' => 'CR',
                'is_active' => true,
            ]
        );

        Station::firstOrCreate(
            ['code' => 'CAR-DOC'],
            [
                'department_id' => $cardio->id,
                'name' => 'Ruang Dokter Jantung',
                'type' => StationType::Doctor,
                'queue_prefix' => 'CD',
                'is_active' => true,
            ]
        );

        // Workflow General
        $genWorkflow = Workflow::firstOrCreate(
            ['department_id' => $general->id, 'name' => 'Workflow Umum'],
            ['is_active' => true]
        );

        WorkflowStep::firstOrCreate(
            ['workflow_id' => $genWorkflow->id, 'station_id' => $registrasiGeneral->id, 'sequence' => 1],
            ['name' => 'Registrasi', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
        );

        WorkflowStep::firstOrCreate(
            ['workflow_id' => $genWorkflow->id, 'station_id' => $nurseGeneral->id, 'sequence' => 2],
            ['name' => 'Perawatan', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
        );

        WorkflowStep::firstOrCreate(
            ['workflow_id' => $genWorkflow->id, 'station_id' => $doctor1->id, 'sequence' => 3],
            ['name' => 'Pemeriksaan Dokter', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
        );

        WorkflowStep::firstOrCreate(
            ['workflow_id' => $genWorkflow->id, 'station_id' => $pharmacy->id, 'sequence' => 4],
            ['name' => 'Apotek', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
        );

        // Workflow Dental
        $dentalWorkflow = Workflow::firstOrCreate(
            ['department_id' => $dental->id, 'name' => 'Workflow Gigi'],
            ['is_active' => true]
        );

        $dentalReg = Station::where('code', 'DNT-REG')->first();
        $dentalDoc = Station::where('code', 'DNT-DOC')->first();

        if ($dentalReg && $dentalDoc) {
            WorkflowStep::firstOrCreate(
                ['workflow_id' => $dentalWorkflow->id, 'station_id' => $dentalReg->id, 'sequence' => 1],
                ['name' => 'Registrasi Gigi', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
            );
            WorkflowStep::firstOrCreate(
                ['workflow_id' => $dentalWorkflow->id, 'station_id' => $dentalDoc->id, 'sequence' => 2],
                ['name' => 'Pemeriksaan Gigi', 'is_active' => true, 'is_optional' => false, 'is_repeatable' => false, 'can_skip' => false, 'completion_requirements' => null, 'entry_conditions' => null]
            );
        }
    }
}
