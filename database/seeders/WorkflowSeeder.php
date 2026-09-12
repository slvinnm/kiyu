<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStep;
use Illuminate\Database\Seeder;

class WorkflowSeeder extends Seeder
{
    public function run(): void
    {
        // Create a workflow for Poli Umum
        $dept = Department::where('code', 'POLIUM')->first();
        $work = Workflow::create([
            'department_id' => $dept->id,
            'name' => 'Poli Umum Workflow',
            'is_active' => true,
        ]);

        $version = WorkflowVersion::create([
            'workflow_id' => $work->id,
            'version_number' => 1,
            'is_active' => true,
            'published_at' => now(),
        ]);

        // Create workflow steps following typical multi-step workflow
        $steps = [
            [
                'sequence' => 1,
                'name' => 'Registration',
                'station_id' => 1, // Registration Umum
                'requires_queue' => true,
                'is_optional' => false,
                'is_repeatable' => false,
                'can_skip' => false,
            ],
            [
                'sequence' => 2,
                'name' => 'Nurse Assessment',
                'station_id' => 2, // Nurse Umum
                'requires_queue' => true,
                'is_optional' => false,
                'is_repeatable' => false,
                'can_skip' => false,
            ],
            [
                'sequence' => 3,
                'name' => 'Doctor Consultation',
                'station_id' => 3, // Doctor Umum
                'requires_queue' => true,
                'is_optional' => false,
                'is_repeatable' => false,
                'can_skip' => false,
            ],
        ];

        foreach ($steps as $step) {
            WorkflowStep::create(array_merge($step, [
                'workflow_version_id' => $version->id,
            ]));
        }
    }
}