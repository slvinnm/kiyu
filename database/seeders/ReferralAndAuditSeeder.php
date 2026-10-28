<?php

namespace Database\Seeders;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Referral;
use App\Models\Visit;
use App\Models\Workflow;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ReferralAndAuditSeeder extends Seeder
{
    public function run(): void
    {
        $general = Department::where('code', 'GEN')->first();
        $dental = Department::where('code', 'DNT')->first();
        $patients = Patient::limit(5)->get();

        if ($general && $dental && $patients->count() >= 2) {
            // Create a visit in General
            $visitSource = Visit::firstOrCreate(
                [
                    'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-REF01',
                ],
                [
                    'patient_id' => $patients[0]->id,
                    'department_id' => $general->id,
                    'workflow_id' => Workflow::where('department_id', $general->id)->first()?->id,
                    'priority' => Priority::Normal,
                    'intake_channel' => IntakeChannel::Manual,
                    'status' => VisitStatus::Waiting,
                    'checked_in_at' => now()->subMinutes(30),
                ]
            );

            // Create a referral to Dental (this simulates a clinical referral)
            $targetDept = Department::where('code', 'DNT')->first();
            if ($targetDept && $visitSource->exists) {
                // Create target visit
                $targetVisit = Visit::create([
                    'patient_id' => $patients[0]->id,
                    'department_id' => $targetDept->id,
                    'workflow_id' => Workflow::where('department_id', $targetDept->id)->value('id'),
                    'visit_number' => 'V-' . Carbon::today()->format('Ymd') . '-REF02',
                    'priority' => Priority::Normal,
                    'intake_channel' => IntakeChannel::Manual,
                    'status' => VisitStatus::Waiting,
                    'registered_by' => 'reception@kiyu.id',
                    'checked_in_at' => now()->subMinutes(15),
                ]);

                // Create referral record
                $referral = Referral::create([
                    'source_visit_id' => $visitSource->id,
                    'target_department_id' => $targetDept->id,
                    'target_visit_id' => $targetVisit->id,
                    'referred_by_user_id' => 'reception@kiyu.id',
                    'status' => ReferralStatus::Accepted,
                    'priority' => Priority::Normal,
                    'reason' => 'Pasien memerlukan pemeriksaan lanjutan di poli gigi',
                ]);
            }
        }
    }
}
