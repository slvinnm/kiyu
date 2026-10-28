<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\QueueEventType;
use App\Enums\QueueTicketStatus;
use App\Enums\VisitStatus;
use App\Exceptions\BusinessConflictException;
use App\Exceptions\InvalidStateException;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Models\QueueEvent;
use App\Models\QueueTicket;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IntakeService
{
    public function __construct(
        public SequenceService $sequenceService,
        public WorkflowService $workflowService,
        public AuditService $auditService
    ) {}

    /**
     * Create a Kiosk walk-in intake.
     *
     * @return array{visit: Visit, acquisition: QueueAcquisition, ticket: QueueTicket}
     */
    public function createKioskIntake(int $departmentId, int $priority = 1, ?string $idempotencyKey = null): array
    {
        return $this->processPhysicalIntake(
            departmentId: $departmentId,
            channel: IntakeChannel::Kiosk,
            priority: Priority::tryFrom($priority) ?? Priority::Normal,
            idempotencyKey: $idempotencyKey
        );
    }

    /**
     * Create a Manual walk-in intake by reception.
     *
     * @return array{visit: Visit, acquisition: QueueAcquisition, ticket: QueueTicket}
     */
    public function createManualIntake(
        int $departmentId,
        int $priority = 1,
        ?string $idempotencyKey = null,
        ?string $registeredBy = null
    ): array {
        return $this->processPhysicalIntake(
            departmentId: $departmentId,
            channel: IntakeChannel::Manual,
            priority: Priority::tryFrom($priority) ?? Priority::Normal,
            idempotencyKey: $idempotencyKey,
            registeredBy: $registeredBy
        );
    }

    /**
     * Create an Online booking intake for an existing patient.
     *
     * @return array{visit: Visit, acquisition: QueueAcquisition, ticket: null}
     */
    public function createOnlineBooking(
        int $patientId,
        int $departmentId,
        int $priority = 1,
        ?string $idempotencyKey = null
    ): array {
        $patient = Patient::query()->findOrFail($patientId);
        $workflow = $this->workflowService->resolvePrimaryWorkflow($departmentId);

        if ($idempotencyKey) {
            $existingAcq = QueueAcquisition::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingAcq) {
                if ($existingAcq->channel === IntakeChannel::Online && $existingAcq->department_id === $departmentId) {
                    return [
                        'visit' => $existingAcq->visit->load(['patient', 'department', 'workflow']),
                        'acquisition' => $existingAcq,
                        'ticket' => null,
                    ];
                }
                throw new BusinessConflictException("Idempotency key '{$idempotencyKey}' conflict with different parameters.");
            }
        }

        return DB::transaction(function () use ($patient, $departmentId, $workflow, $priority, $idempotencyKey): array {
            $visitNumber = $this->sequenceService->generateVisitNumber();
            $onlineActiveKey = (string) Str::uuid();

            /** @var Visit $visit */
            $visit = Visit::query()->create([
                'patient_id' => $patient->id,
                'department_id' => $departmentId,
                'workflow_id' => $workflow->id,
                'visit_number' => $visitNumber,
                'priority' => Priority::tryFrom($priority) ?? Priority::Normal,
                'intake_channel' => IntakeChannel::Online,
                'status' => VisitStatus::AwaitingCheckin,
                'online_active_key' => $onlineActiveKey,
            ]);

            /** @var QueueAcquisition $acquisition */
            $acquisition = QueueAcquisition::query()->create([
                'visit_id' => $visit->id,
                'department_id' => $departmentId,
                'channel' => IntakeChannel::Online,
                'status' => QueueAcquisitionStatus::Acquired,
                'idempotency_key' => $idempotencyKey,
            ]);

            $this->auditService->log(
                action: 'ONLINE_BOOKING_CREATED',
                entityType: 'Visit',
                entityId: $visit->id,
                metadata: [
                    'visit_number' => $visitNumber,
                    'patient_id' => $patient->id,
                    'department_id' => $departmentId,
                ]
            );

            return [
                'visit' => $visit->load(['patient', 'department', 'workflow']),
                'acquisition' => $acquisition,
                'ticket' => null,
            ];
        });
    }

    /**
     * Preview / lookup online booking without checking in (for kiosk screen).
     *
     * @return array{visit: Visit, patient_preview: array{name: string, mrn: string}, department: string, is_eligible_checkin: bool, status: string}
     */
    public function lookupOnlineBooking(string $bookingIdentifier): array
    {
        /** @var Visit|null $visit */
        $visit = Visit::query()
            ->with(['patient', 'department', 'workflow'])
            ->where('online_active_key', $bookingIdentifier)
            ->orWhere('visit_number', $bookingIdentifier)
            ->orWhere('id', is_numeric($bookingIdentifier) ? (int) $bookingIdentifier : 0)
            ->first();

        if (! $visit) {
            throw new InvalidStateException("Visit not found for booking identifier '{$bookingIdentifier}'.");
        }

        // Mask patient name for kiosk privacy (e.g., "Budi Santoso" -> "Bu** Sa*****")
        $name = $visit->patient?->name ?? 'Unknown';
        $words = explode(' ', $name);
        $maskedWords = array_map(function ($word) {
            $len = strlen($word);
            if ($len <= 2) {
                return $word;
            }

            return substr($word, 0, 2) . str_repeat('*', $len - 2);
        }, $words);
        $maskedName = implode(' ', $maskedWords);

        return [
            'visit_number' => $visit->visit_number,
            'booking_identifier' => $visit->online_active_key,
            'patient_preview' => [
                'masked_name' => $maskedName,
                'mrn' => $visit->patient?->mrn ?? '-',
            ],
            'department_name' => $visit->department?->name ?? '-',
            'priority' => $visit->priority->value,
            'status' => $visit->status->value,
            'is_eligible_checkin' => $visit->status === VisitStatus::AwaitingCheckin,
        ];
    }

    /**
     * Perform online check-in at kiosk to enter reception queue.
     *
     * @return array{visit: Visit, ticket: QueueTicket}
     */
    public function performOnlineCheckin(string $bookingIdentifier): array
    {
        return DB::transaction(function () use ($bookingIdentifier): array {
            /** @var Visit|null $visit */
            $visit = Visit::query()
                ->where('online_active_key', $bookingIdentifier)
                ->orWhere('visit_number', $bookingIdentifier)
                ->orWhere('id', is_numeric($bookingIdentifier) ? (int) $bookingIdentifier : 0)
                ->lockForUpdate()
                ->first();

            if (! $visit) {
                throw new InvalidStateException("Visit not found for booking identifier '{$bookingIdentifier}'.");
            }

            if ($visit->status !== VisitStatus::AwaitingCheckin) {
                throw new InvalidStateException("Visit #{$visit->visit_number} is in status '{$visit->status->value}', cannot check in.");
            }

            $workflow = $visit->workflow;
            $registrationStep = $this->workflowService->resolveRegistrationStep($workflow);
            $registrationStation = $registrationStep->station;

            $numbering = $this->sequenceService->generateQueueNumber($registrationStation);

            $visit->update([
                'status' => VisitStatus::Waiting,
                'checked_in_at' => now(),
            ]);

            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()->create([
                'visit_id' => $visit->id,
                'workflow_step_id' => $registrationStep->id,
                'station_id' => $registrationStation->id,
                'queue_number' => $numbering['queue_number'],
                'priority' => $visit->priority,
                'internal_sequence' => $numbering['internal_sequence'],
                'status' => QueueTicketStatus::Created,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Created,
                'from_status' => null,
                'to_status' => QueueTicketStatus::Created->value,
                'payload' => ['reason' => 'Online self check-in verified at kiosk'],
            ]);

            $this->auditService->log(
                action: 'ONLINE_CHECKIN_COMPLETED',
                entityType: 'Visit',
                entityId: $visit->id,
                metadata: [
                    'queue_number' => $ticket->queue_number,
                    'station_id' => $registrationStation->id,
                ]
            );

            return [
                'visit' => $visit->fresh(['patient', 'department', 'workflow']),
                'ticket' => $ticket->load(['station', 'workflowStep']),
            ];
        });
    }

    /**
     * Shared logic for physical walk-in (Kiosk or Manual).
     *
     * @return array{visit: Visit, acquisition: QueueAcquisition, ticket: QueueTicket}
     */
    protected function processPhysicalIntake(
        int $departmentId,
        IntakeChannel $channel,
        Priority $priority,
        ?string $idempotencyKey = null,
        ?string $registeredBy = null
    ): array {
        if ($idempotencyKey) {
            $existingAcq = QueueAcquisition::query()
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existingAcq) {
                if ($existingAcq->channel === $channel && $existingAcq->department_id === $departmentId) {
                    $visit = $existingAcq->visit->load(['patient', 'department', 'workflow']);
                    $ticket = $visit->activeQueueTicket ?? $visit->queueTickets()->latest('id')->first();

                    return [
                        'visit' => $visit,
                        'acquisition' => $existingAcq,
                        'ticket' => $ticket,
                    ];
                }
                throw new BusinessConflictException("Idempotency key '{$idempotencyKey}' conflict with different parameters.");
            }
        }

        $workflow = $this->workflowService->resolvePrimaryWorkflow($departmentId);
        $registrationStep = $this->workflowService->resolveRegistrationStep($workflow);
        $registrationStation = $registrationStep->station;

        return DB::transaction(function () use (
            $departmentId,
            $channel,
            $priority,
            $idempotencyKey,
            $registeredBy,
            $workflow,
            $registrationStep,
            $registrationStation
        ): array {
            $visitNumber = $this->sequenceService->generateVisitNumber();
            $numbering = $this->sequenceService->generateQueueNumber($registrationStation);

            /** @var Visit $visit */
            $visit = Visit::query()->create([
                'patient_id' => null,
                'department_id' => $departmentId,
                'workflow_id' => $workflow->id,
                'visit_number' => $visitNumber,
                'priority' => $priority,
                'intake_channel' => $channel,
                'status' => VisitStatus::Waiting,
                'registered_by' => $registeredBy,
                'checked_in_at' => now(),
            ]);

            /** @var QueueAcquisition $acquisition */
            $acquisition = QueueAcquisition::query()->create([
                'visit_id' => $visit->id,
                'department_id' => $departmentId,
                'channel' => $channel,
                'status' => QueueAcquisitionStatus::Acquired,
                'idempotency_key' => $idempotencyKey,
                'registered_by' => $registeredBy,
            ]);

            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()->create([
                'visit_id' => $visit->id,
                'workflow_step_id' => $registrationStep->id,
                'station_id' => $registrationStation->id,
                'queue_number' => $numbering['queue_number'],
                'priority' => $priority,
                'internal_sequence' => $numbering['internal_sequence'],
                'status' => QueueTicketStatus::Created,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Created,
                'from_status' => null,
                'to_status' => QueueTicketStatus::Created->value,
                'payload' => ['channel' => $channel->value],
            ]);

            $this->auditService->log(
                action: 'PHYSICAL_INTAKE_CREATED',
                entityType: 'Visit',
                entityId: $visit->id,
                metadata: [
                    'channel' => $channel->value,
                    'visit_number' => $visitNumber,
                    'queue_number' => $ticket->queue_number,
                ]
            );

            return [
                'visit' => $visit->load(['patient', 'department', 'workflow']),
                'acquisition' => $acquisition,
                'ticket' => $ticket->load(['station', 'workflowStep']),
            ];
        });
    }
}
