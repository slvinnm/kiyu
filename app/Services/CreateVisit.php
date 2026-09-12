<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVisit
{
    /**
     * Create a visit through any intake channel (ONLINE, KIOSK, WALK_IN).
     *
     * @param  string  $departmentCode  e.g., 'POLIUM'
     * @param  int|null  $priority  Override priority (for internal use only - API should NOT accept this)
     *
     * @throws ValidationException
     */
    public function handle(int $patientId, string $departmentCode, IntakeChannel $intakeChannel, ?int $priority = null): Visit
    {
        return DB::transaction(function () use ($patientId, $departmentCode, $intakeChannel, $priority) {
            // 1. Find patient
            $patient = Patient::findOrFail($patientId);

            // 2. Find department/poli by code
            $department = Department::where('code', $departmentCode)->where('is_active', true)->firstOrFail();

            // 3. Resolve the active workflow version for this department
            $workflow = $department->workflows()->where('is_active', true)->first();
            if (! $workflow) {
                throw new \LogicException("No active workflow found for department {$department->code}");
            }

            $workflowVersion = WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('is_active', true)
                ->firstOrFail();

            // 4. Create the visit
            $visitPriority = $priority ?? Priority::NORMAL->value;
            if (! in_array($visitPriority, [Priority::NORMAL->value, Priority::PRIORITY->value, Priority::EMERGENCY->value])) {
                $visitPriority = Priority::NORMAL->value;
            }

            $visit = Visit::create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
                'workflow_version_id' => $workflowVersion->id,
                'visit_number' => $this->generateVisitNumber(),
                'priority' => $visitPriority,
                'intake_channel' => $intakeChannel->value,
                'status' => $this->resolveInitialStatus($intakeChannel),
                // registered_by will be set by the caller if applicable (e.g., receptionist)
            ]);

            // 5. Start at the actual first workflow step. WorkflowEngine
            // automatically completes consecutive non-queue steps.
            $firstStep = $workflowVersion->steps()->first();

            // 7. If the first step requires a queue, create the initial queue ticket
            if ($firstStep) {
                $workflowEngine = new WorkflowEngine;
                $initialTicket = $workflowEngine->createFromIntake($visit, $firstStep->sequence);

                // Override priority if specified (internal only)
                if ($initialTicket && $priority !== null) {
                    $initialTicket->update(['priority' => $priority]);
                }
            }

            return $visit->fresh();
        });
    }

    private function resolveInitialStatus(IntakeChannel $channel): string
    {
        // Per lifecycle rules:
        // - ONLINE: patient registers remotely; arrives physically later → AWAITING_CHECKIN
        // - KIOSK: patient is physically present and ready to wait
        // - WALK_IN: patient is physically present and ready to wait
        return match ($channel) {
            IntakeChannel::ONLINE => VisitStatus::AWAITING_CHECKIN->value,
            IntakeChannel::KIOSK, IntakeChannel::WALK_IN => VisitStatus::WAITING->value,
        };
    }

    private function generateVisitNumber(): string
    {
        $counter = new VisitCounter;
        $nextNumber = $counter->incrementAndGet();

        $date = now()->format('y-m-d');

        return sprintf('V-%s-%05d', str_replace('-', '', $date), $nextNumber);
    }
}
