<?php

namespace Tests;

use App\Enums\StationType;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Station;
use App\Models\Workflow;
use App\Models\WorkflowStep;

trait SetupKiyuHospital
{
    protected Department $generalDepartment;

    protected Station $registrationStation;

    protected Station $nurseStation;

    protected Station $doctorStation1;

    protected Station $doctorStation2;

    protected Station $pharmacyStation;

    protected Workflow $generalWorkflow;

    protected WorkflowStep $regStep;

    protected WorkflowStep $nurseStep;

    protected WorkflowStep $docStep;

    protected WorkflowStep $pharmStep;

    protected Department $dentalDepartment;

    protected Station $dentalDoctorStation;

    protected Workflow $dentalWorkflow;

    protected WorkflowStep $dentalStep;

    protected Patient $samplePatient;

    protected function setupHospital(): void
    {
        // General Department
        $this->generalDepartment = Department::create([
            'code' => 'POLI_UMUM',
            'name' => 'Poli Umum',
            'is_active' => true,
        ]);

        $this->registrationStation = Station::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Meja Registrasi 1',
            'code' => 'REG-01',
            'type' => StationType::Registration,
            'queue_prefix' => 'R',
            'is_active' => true,
        ]);

        $this->nurseStation = Station::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Stasiun Perawat',
            'code' => 'NUR-01',
            'type' => StationType::Nurse,
            'queue_prefix' => 'N',
            'is_active' => true,
        ]);

        $this->doctorStation1 = Station::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Ruang Dokter 1',
            'code' => 'DOC-01',
            'type' => StationType::Doctor,
            'queue_prefix' => 'D',
            'is_active' => true,
        ]);

        $this->doctorStation2 = Station::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Ruang Dokter 2',
            'code' => 'DOC-02',
            'type' => StationType::Doctor,
            'queue_prefix' => 'D2',
            'is_active' => true,
        ]);

        $this->pharmacyStation = Station::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Apotek',
            'code' => 'PHARM-01',
            'type' => StationType::Pharmacy,
            'queue_prefix' => 'P',
            'is_active' => true,
        ]);

        $this->generalWorkflow = Workflow::create([
            'department_id' => $this->generalDepartment->id,
            'name' => 'Alur Rawat Jalan Umum',
            'is_active' => true,
        ]);

        $this->regStep = WorkflowStep::create([
            'workflow_id' => $this->generalWorkflow->id,
            'station_id' => $this->registrationStation->id,
            'name' => 'Registrasi Pasien',
            'sequence' => 1,
            'is_active' => true,
        ]);

        $this->nurseStep = WorkflowStep::create([
            'workflow_id' => $this->generalWorkflow->id,
            'station_id' => $this->nurseStation->id,
            'name' => 'Pemeriksaan Awal Perawat',
            'sequence' => 2,
            'is_active' => true,
        ]);

        $this->docStep = WorkflowStep::create([
            'workflow_id' => $this->generalWorkflow->id,
            'station_id' => $this->doctorStation1->id,
            'name' => 'Konsultasi Dokter',
            'sequence' => 3,
            'is_active' => true,
        ]);

        $this->pharmStep = WorkflowStep::create([
            'workflow_id' => $this->generalWorkflow->id,
            'station_id' => $this->pharmacyStation->id,
            'name' => 'Pengambilan Obat',
            'sequence' => 4,
            'is_active' => true,
        ]);

        // Dental Department for Referrals
        $this->dentalDepartment = Department::create([
            'code' => 'POLI_GIGI',
            'name' => 'Poli Gigi',
            'is_active' => true,
        ]);

        $dentalRegStation = Station::create([
            'department_id' => $this->dentalDepartment->id,
            'name' => 'Registrasi Gigi',
            'code' => 'REG-GIGI',
            'type' => StationType::Registration,
            'queue_prefix' => 'RG',
            'is_active' => true,
        ]);

        $this->dentalDoctorStation = Station::create([
            'department_id' => $this->dentalDepartment->id,
            'name' => 'Ruang Dokter Gigi',
            'code' => 'DENT-01',
            'type' => StationType::Doctor,
            'queue_prefix' => 'G',
            'is_active' => true,
        ]);

        $this->dentalWorkflow = Workflow::create([
            'department_id' => $this->dentalDepartment->id,
            'name' => 'Alur Rawat Gigi',
            'is_active' => true,
        ]);

        WorkflowStep::create([
            'workflow_id' => $this->dentalWorkflow->id,
            'station_id' => $dentalRegStation->id,
            'name' => 'Registrasi Gigi',
            'sequence' => 1,
            'is_active' => true,
        ]);

        $this->dentalStep = WorkflowStep::create([
            'workflow_id' => $this->dentalWorkflow->id,
            'station_id' => $this->dentalDoctorStation->id,
            'name' => 'Pemeriksaan Gigi',
            'sequence' => 2,
            'is_active' => true,
        ]);

        // Sample Patient
        $this->samplePatient = Patient::create([
            'mrn' => 'MRN-10001',
            'nik' => '3201010101900001',
            'name' => 'Budi Santoso',
            'date_of_birth' => '1990-01-01',
            'gender' => 'male',
            'phone' => '081234567890',
            'email' => 'budi@example.com',
            'address' => 'Jl. Merdeka No. 10',
        ]);
    }
}
