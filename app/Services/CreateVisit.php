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

class CreateVisit
{
    public function handle(
        int $patientId,
        string $departmentCode,
        IntakeChannel $intakeChannel,
        ?int $priority = null,
        ?int $registeredByUserId = null,
        ?string $onlineActiveKey = null,
    ): Visit {
        return DB::transaction(function () use ($patientId, $departmentCode, $intakeChannel, $priority, $registeredByUserId, $onlineActiveKey) {
            $patient = Patient::findOrFail($patientId);
            $department = Department::where('code', $departmentCode)->where('is_active', true)->firstOrFail();

            $workflows = $department->workflows()->where('is_active', true)->orderBy('id')->get();
            if ($workflows->count() !== 1) {
                throw new \LogicException("Department {$department->code} must have exactly one active workflow.");
            }
            $workflow = $workflows->first();

            $workflowVersions = WorkflowVersion::where('workflow_id', $workflow->id)
                ->where('is_active', true)
                ->orderByDesc('version_number')
                ->get();
            if ($workflowVersions->count() !== 1) {
                throw new \LogicException("Workflow {$workflow->id} must have exactly one active version.");
            }
            $workflowVersion = $workflowVersions->first();

            $firstStep = $workflowVersion->steps()->orderBy('sequence')->first();
            if (! $firstStep) {
                throw new \LogicException("Workflow version {$workflowVersion->id} has no steps");
            }

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
                'registered_by' => $registeredByUserId,
                'online_active_key' => $onlineActiveKey,
            ]);

            $initialTicket = app(WorkflowEngine::class)->createFromIntake($visit, $firstStep->sequence);
            if (! $initialTicket) {
                throw new \LogicException('Unable to create the initial queue ticket');
            }

            return $visit->fresh();
        });
    }

    private function resolveInitialStatus(IntakeChannel $channel): string
    {
        return match ($channel) {
            IntakeChannel::ONLINE => VisitStatus::AWAITING_CHECKIN->value,
            IntakeChannel::KIOSK,
            IntakeChannel::WALK_IN,
            IntakeChannel::MANUAL => VisitStatus::WAITING->value,
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
