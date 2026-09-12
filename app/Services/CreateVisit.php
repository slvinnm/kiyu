<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\VisitStatus;
use App\Enums\Priority;
use App\Models\Department;
use App\Models\Patient;
use App\Models\Visit;
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
            $visit = Visit::create([
                'patient_id' => $patient->id,
                'department_id' => $department->id,
                'workflow_version_id' => $workflowVersion->id,
                'visit_number' => $this->generateVisitNumber(),
                'intake_channel' => $intakeChannel->value,
                'status' => VisitStatus::AWAITING_CHECKIN->value,
                // registered_by will be set by the caller if applicable (e.g., receptionist)
            ]);

            // 5. Determine initial priority - backend-controlled only
            // Priority must NOT come from public API; use trusted business rules
            $visitPriority = $priority ?? Priority::NORMAL->value;
            if (!in_array($visitPriority, [Priority::NORMAL->value, Priority::PRIORITY->value, Priority::EMERGENCY->value])) {
                $visitPriority = Priority::NORMAL->value;
            }

            // 6. Get the first workflow step that requires a queue
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

    /**
     * Generate a unique visit number (e.g., V-20260912-00001).
     * Format: V-YYMMDD-SEQ
     */
    private function generateVisitNumber(): string
    {
        $date = now()->format('y-m-d');
        $counterKey = "visit_counter_{$date}";

        // Simple atomic counter - in production you might use a dedicated table
        $last = cache()->get($counterKey, 0);
        $next = $last + 1;
        cache()->put($counterKey, $next, 60 * 24); // Cache for 24 hours

        return sprintf('V-%s-%05d', str_replace('-', '', $date), $next);
    }
}