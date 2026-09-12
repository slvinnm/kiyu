<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\VisitStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\WorkflowVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueAcquisitionService
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
    ) {}

    /**
     * Acquire a queue number at a kiosk before the patient's identity is
     * registered. The resulting visit is intentionally patient-less until
     * reception completes registration.
     */
    public function acquire(string $departmentCode, ?string $idempotencyKey = null): QueueAcquisition
    {
        return DB::transaction(function () use ($departmentCode, $idempotencyKey) {
            if ($idempotencyKey) {
                $existing = QueueAcquisition::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->department?->code !== $departmentCode) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'The idempotency key has already been used for another department.',
                        ]);
                    }

                    return $existing->load(['department', 'visit.queueTickets']);
                }
            }

            $department = Department::query()
                ->where('code', $departmentCode)
                ->where('is_active', true)
                ->firstOrFail();

            $workflows = $department->workflows()
                ->where('is_active', true)
                ->orderBy('id')
                ->get();

            if ($workflows->count() !== 1) {
                throw new \LogicException(
                    "Department {$department->code} must have exactly one active workflow."
                );
            }

            $workflow = $workflows->first();

            $workflowVersions = WorkflowVersion::query()
                ->where('workflow_id', $workflow->id)
                ->where('is_active', true)
                ->orderByDesc('version_number')
                ->get();

            if ($workflowVersions->count() !== 1) {
                throw new \LogicException(
                    "Workflow {$workflow->id} must have exactly one active version."
                );
            }

            $workflowVersion = $workflowVersions->first();

            $firstStep = $workflowVersion->steps()
                ->orderBy('sequence')
                ->first();

            if (! $firstStep) {
                throw new \LogicException("Workflow version {$workflowVersion->id} has no steps");
            }

            if ($idempotencyKey) {
                $existing = QueueAcquisition::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    if ($existing->department_id !== $department->id) {
                        throw ValidationException::withMessages([
                            'idempotency_key' => 'The idempotency key has already been used for another department.',
                        ]);
                    }

                    return $existing->load(['department', 'visit.queueTickets']);
                }
            }

            $visit = Visit::create([
                'patient_id' => null,
                'department_id' => $department->id,
                'workflow_version_id' => $workflowVersion->id,
                'visit_number' => $this->generateVisitNumber(),
                'priority' => Priority::NORMAL->value,
                'intake_channel' => IntakeChannel::KIOSK->value,
                'status' => VisitStatus::WAITING->value,
            ]);

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
                'idempotency_key' => $idempotencyKey,
                'status' => QueueAcquisitionStatus::ACQUIRED->value,
                'acquired_at' => now(),
            ])->load(['department', 'visit.queueTickets']);
        });
    }

    /**
     * Attach the real patient identity when reception completes kiosk
     * registration. Queue acquisition remains the intake record; it does not
     * become a second Visit or a second queue ticket.
     */
    public function registerPatient(
        QueueAcquisition $acquisition,
        Patient $patient,
        User $registeredBy,
    ): QueueAcquisition {
        return DB::transaction(function () use ($acquisition, $patient, $registeredBy) {
            $lockedAcquisition = QueueAcquisition::query()
                ->whereKey($acquisition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAcquisition->status !== QueueAcquisitionStatus::ACQUIRED) {
                throw ValidationException::withMessages([
                    'acquisition' => 'This queue acquisition is no longer available for registration.',
                ]);
            }

            $visit = Visit::query()
                ->whereKey($lockedAcquisition->visit_id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($visit->patient_id !== null && $visit->patient_id !== $patient->id) {
                throw ValidationException::withMessages([
                    'patient' => 'This queue acquisition is already linked to another patient.',
                ]);
            }

            if ($visit->intake_channel !== IntakeChannel::KIOSK) {
                throw ValidationException::withMessages([
                    'acquisition' => 'Only kiosk visits can be registered through a queue acquisition.',
                ]);
            }

            $visit->update([
                'patient_id' => $patient->id,
                'registered_by' => $registeredBy->id,
            ]);

            $lockedAcquisition->update([
                'status' => QueueAcquisitionStatus::REGISTERED->value,
                'registered_by' => $registeredBy->id,
                'registered_at' => now(),
            ]);

            return $lockedAcquisition->fresh(['department', 'visit.patient', 'visit.queueTickets']);
        });
    }

    public function cancel(QueueAcquisition $acquisition): QueueAcquisition
    {
        return DB::transaction(function () use ($acquisition) {
            $lockedAcquisition = QueueAcquisition::query()
                ->whereKey($acquisition->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedAcquisition->status !== QueueAcquisitionStatus::ACQUIRED) {
                throw ValidationException::withMessages([
                    'acquisition' => 'Only an acquired queue can be cancelled.',
                ]);
            }

            $lockedAcquisition->update([
                'status' => QueueAcquisitionStatus::CANCELLED->value,
            ]);

            return $lockedAcquisition->fresh(['department', 'visit.queueTickets']);
        });
    }

    private function generateVisitNumber(): string
    {
        $counter = new VisitCounter;
        $nextNumber = $counter->incrementAndGet();

        return sprintf('V-%s-%05d', now()->format('ymd'), $nextNumber);
    }
}
