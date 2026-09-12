<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\QueueAcquisition;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;

class QueueAcquisitionService
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
    ) {
    }

    /**
     * Acquire a queue number at a kiosk before the patient's identity is
     * registered. The resulting visit is intentionally patient-less until
     * reception completes registration.
     */
    public function acquire(string $departmentCode): QueueAcquisition
    {
        return DB::transaction(function () use ($departmentCode) {
            $department = Department::query()
                ->where('code', $departmentCode)
                ->where('is_active', true)
                ->firstOrFail();

            $workflow = $department->workflows()
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

            if (! $workflow) {
                throw new \LogicException("No active workflow found for department {$department->code}");
            }

            $workflowVersion = WorkflowVersion::query()
                ->where('workflow_id', $workflow->id)
                ->where('is_active', true)
                ->orderByDesc('version_number')
                ->firstOrFail();

            $visit = Visit::create([
                'patient_id' => null,
                'department_id' => $department->id,
                'workflow_version_id' => $workflowVersion->id,
                'visit_number' => $this->generateVisitNumber(),
                'priority' => Priority::NORMAL->value,
                'intake_channel' => IntakeChannel::KIOSK->value,
                'status' => VisitStatus::WAITING->value,
            ]);

            $firstStep = $workflowVersion->steps()
                ->orderBy('sequence')
                ->first();

            if (! $firstStep) {
                throw new \LogicException("Workflow version {$workflowVersion->id} has no steps");
            }

            $ticket = $this->workflowEngine->createFromIntake(
                visit: $visit,
                initialStepSequence: $firstStep->sequence,
                context: ['intake_channel' => IntakeChannel::KIOSK->value],
            );

            if (! $ticket) {
                throw new \LogicException('Unable to create the initial queue ticket');
            }

            return QueueAcquisition::create([
                'department_id' => $department->id,
                'visit_id' => $visit->id,
                'channel' => IntakeChannel::KIOSK->value,
                'status' => QueueAcquisitionStatus::ACQUIRED->value,
                'acquired_at' => now(),
            ])->load(['department', 'visit.queueTickets']);
        });
    }

    private function generateVisitNumber(): string
    {
        $counter = new VisitCounter;
        $nextNumber = $counter->incrementAndGet();

        return sprintf('V-%s-%05d', now()->format('ymd'), $nextNumber);
    }
}
