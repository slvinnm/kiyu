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
            $patient = Patient::findOrFail($patientId);

            $department = Department::where('code', $departmentCode)
                ->where('is_active', true)
                ->firstOrFail();

            $workflow = $department->workflows()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (! $workflow) {
                throw new \LogicException("No active workflow found for department {$department->code}");
            }

            $workflowVersion = WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('is_active', true)
                ->orderBy('version_number')
                ->firstOrFail();

            $visitPriority = $priority ?? Priority::NORMAL->value;
            if (! in_array($visitPriority, [
                Priority::NORMAL->value,
                Priority::PRIORITY->value,
                Priority::EMERGENCY->value,
            ], true)) {
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
            ]);

            $firstStep = $workflowVersion->steps()
                ->orderBy('sequence')
                ->first();

            if ($firstStep) {
                $workflowEngine = new WorkflowEngine;
                $initialTicket = $workflowEngine->createFromIntake($visit, $firstStep->sequence);

                if ($initialTicket && $priority !== null) {
                    $initialTicket->update(['priority' => $priority]);
                }
            }

            return $visit->fresh();
        });
    }

    private function resolveInitialStatus(IntakeChannel $channel): string
    {
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
