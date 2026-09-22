<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueStatus;
use App\Enums\StationType;
use App\Enums\VisitStatus;
use App\Enums\VisitWorkflowStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\Department;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Models\QueueTicket;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitCounter;
use App\Models\WorkflowVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueAcquisitionService
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
        private CreateVisit $createVisit,
    ) {}

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

                    return $existing->load([
                        'department',
                        'visit.patient',
                        'visit.queueTickets.station',
                    ]);
                }
            }

            $department = $this->resolveDepartment($departmentCode);
            $workflowVersion = $this->resolveWorkflowVersion($department);
            $firstStep = $workflowVersion->steps()->orderBy('sequence')->first();

            if (! $firstStep) {
                throw new \LogicException("Workflow version {$workflowVersion->id} has no steps");
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
            ])->load([
                'department',
                'visit.patient',
                'visit.queueTickets.station',
            ]);
        });
    }

    public function createForVisit(Visit $visit, IntakeChannel $channel): QueueAcquisition
    {
        if ($visit->intake_channel !== $channel) {
            throw new \LogicException(
                "Queue acquisition channel {$channel->value} does not match visit channel {$visit->intake_channel->value}."
            );
        }

        $existing = QueueAcquisition::query()
            ->where('visit_id', $visit->id)
            ->first();

        if ($existing) {
            return $existing->load([
                'department',
                'visit.patient',
                'visit.queueTickets.station',
            ]);
        }

        return QueueAcquisition::create([
            'department_id' => $visit->department_id,
            'visit_id' => $visit->id,
            'channel' => $channel->value,
            'status' => QueueAcquisitionStatus::ACQUIRED->value,
            'acquired_at' => now(),
        ])->load([
            'department',
            'visit.patient',
            'visit.queueTickets.station',
        ]);
    }

    public function createReception(
        array $data,
        User $createdBy,
        IntakeChannel $channel = IntakeChannel::MANUAL,
    ): QueueAcquisition {
        return DB::transaction(function () use ($data, $createdBy, $channel) {
            $patient = isset($data['patient_id'])
                ? Patient::query()->findOrFail($data['patient_id'])
                : Patient::create(Arr::only($data, [
                    'name',
                    'email',
                    'national_id',
                    'date_of_birth',
                    'gender',
                    'phone',
                    'address',
                ]));

            $visit = $this->createVisit->handle(
                patientId: $patient->id,
                departmentCode: $data['department_code'],
                intakeChannel: $channel,
                registeredByUserId: $createdBy->id,
            );

            return $this->createForVisit($visit, $channel);
        });
    }

    public function attachPatient(
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

            if ($visit->patient_id === null) {
                $visit->update([
                    'patient_id' => $patient->id,
                ]);
            }

            if ($visit->registered_by === null) {
                $visit->update([
                    'registered_by' => $registeredBy->id,
                ]);
            }

            return $lockedAcquisition->fresh([
                'department',
                'visit.patient',
                'visit.queueTickets.station',
            ]);
        });
    }

    public function validateRegistrationCompletion(QueueTicket $ticket): void
    {
        $ticket->loadMissing(['station', 'visit']);

        if ($ticket->station?->type !== StationType::REGISTRATION) {
            return;
        }

        $acquisition = QueueAcquisition::query()
            ->where('visit_id', $ticket->visit_id)
            ->lockForUpdate()
            ->first();

        if (! $acquisition || $acquisition->status !== QueueAcquisitionStatus::ACQUIRED) {
            return;
        }

        if ($ticket->visit?->patient_id === null) {
            throw ValidationException::withMessages([
                'patient' => 'A patient must be registered before the registration queue can be completed.',
            ]);
        }
    }

    public function markRegisteredAfterCompletion(
        QueueTicket $ticket,
        ?int $registeredByUserId = null,
    ): void {
        $ticket->loadMissing(['station']);

        if ($ticket->station?->type !== StationType::REGISTRATION) {
            return;
        }

        $acquisition = QueueAcquisition::query()
            ->where('visit_id', $ticket->visit_id)
            ->lockForUpdate()
            ->first();

        if (! $acquisition || $acquisition->status !== QueueAcquisitionStatus::ACQUIRED) {
            return;
        }

        $acquisition->update([
            'status' => QueueAcquisitionStatus::REGISTERED->value,
            'registered_by' => $registeredByUserId ?? $acquisition->registered_by,
            'registered_at' => now(),
        ]);
    }

    public function cancelIfRegistrationTicket(QueueTicket $ticket): void
    {
        $ticket->loadMissing(['station']);

        if ($ticket->station?->type !== StationType::REGISTRATION) {
            return;
        }

        $acquisition = QueueAcquisition::query()
            ->where('visit_id', $ticket->visit_id)
            ->lockForUpdate()
            ->first();

        if (! $acquisition || $acquisition->status !== QueueAcquisitionStatus::ACQUIRED) {
            return;
        }

        $acquisition->update([
            'status' => QueueAcquisitionStatus::CANCELLED->value,
        ]);
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

            $visit = Visit::query()
                ->whereKey($lockedAcquisition->visit_id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasProgressedTicket = $visit->queueTickets()
                ->where('status', '!=', QueueStatus::CREATED->value)
                ->exists();

            if ($hasProgressedTicket) {
                throw ValidationException::withMessages([
                    'acquisition' => 'A queue that has already progressed cannot be cancelled through acquisition.',
                ]);
            }

            $stateMachine = new QueueStateMachine;

            $activeTickets = QueueTicket::query()
                ->where('visit_id', $visit->id)
                ->whereIn('status', [
                    QueueStatus::CREATED->value,
                    QueueStatus::CALLED->value,
                    QueueStatus::IN_PROGRESS->value,
                    QueueStatus::ON_HOLD->value,
                ])
                ->lockForUpdate()
                ->get();

            foreach ($activeTickets as $ticket) {
                $stateMachine->apply($ticket, QueueStatus::CANCELLED);
            }

            $visitWorkflow = $visit->visitWorkflow()
                ->lockForUpdate()
                ->first();

            if ($visitWorkflow) {
                $visitWorkflow->update([
                    'status' => VisitWorkflowStatus::CANCELLED->value,
                ]);

                $visitWorkflow->steps()
                    ->whereIn('status', [
                        VisitWorkflowStepStatus::PENDING->value,
                        VisitWorkflowStepStatus::IN_PROGRESS->value,
                    ])
                    ->update([
                        'status' => VisitWorkflowStepStatus::CANCELLED->value,
                    ]);
            }

            $visit->update([
                'status' => VisitStatus::CANCELLED->value,
            ]);

            $lockedAcquisition->update([
                'status' => QueueAcquisitionStatus::CANCELLED->value,
            ]);

            return $lockedAcquisition->fresh([
                'department',
                'visit.patient',
                'visit.visitWorkflow',
                'visit.queueTickets.station',
            ]);
        });
    }

    private function resolveDepartment(string $departmentCode): Department
    {
        return Department::query()
            ->where('code', $departmentCode)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function resolveWorkflowVersion(Department $department): WorkflowVersion
    {
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

        return $workflowVersions->first();
    }

    private function generateVisitNumber(): string
    {
        $counter = new VisitCounter;
        $nextNumber = $counter->incrementAndGet();

        return sprintf('V-%s-%05d', now()->format('ymd'), $nextNumber);
    }
}
