<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\VisitStatus;
use App\Enums\Priority;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\WorkflowVersion;
use App\Models\WorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateVisit
{
    /**
     * Create a visit through any intake channel (ONLINE, KIOSK, WALK_IN).
     *
     * @param int $patientId
     * @param string $departmentCode e.g., 'POLIUM'
     * @param IntakeChannel $intakeChannel
     * @param int|null $priority Override priority (for internal use only - API should NOT accept this)
     * @return Visit
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
            $workflowVersion = WorkflowVersion::where('workflow_id', $department->workflows()->where('is_active', true)->first()->id)
                ->where('is_active', true)
                ->firstOrFail();

            // 4. Create the visit
            $visitPriority = $priority ?? Priority::NORMAL->value;
            if (!in_array($visitPriority, [Priority::NORMAL->value, Priority::PRIORITY->value, Priority::EMERGENCY->value])) {
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

            // 5. Get the first workflow step that requires a queue
            $firstQueueStep = $workflowVersion->steps()
                ->where('requires_queue', true)
                ->orderBy('sequence')
                ->first();

            // 7. If the first step requires a queue, create the initial queue ticket
            if ($firstQueueStep) {
                $workflowEngine = new WorkflowEngine();
                $initialTicket = $workflowEngine->createFromIntake($visit, $firstQueueStep->sequence);

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
        // - KIOSK: patient at station, completes registration → CHECKED_IN (ready for queue)
        // - WALK_IN: patient at reception; check-in handled at registration → CHECKED_IN
        return match ($channel) {
            IntakeChannel::ONLINE => VisitStatus::AWAITING_CHECKIN->value,
            IntakeChannel::KIOSK, IntakeChannel::WALK_IN => VisitStatus::CHECKED_IN->value,
        };
    }
    private function generateVisitNumber(): string
    {
        $counter = new VisitCounter();
        $nextNumber = $counter->incrementAndGet();

        $date = now()->format('y-m-d');
        return sprintf('V-%s-%05d', str_replace('-', '', $date), $nextNumber);
    }
}