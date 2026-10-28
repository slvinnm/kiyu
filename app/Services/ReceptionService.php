<?php

namespace App\Services;

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

class ReceptionService
{
    public function __construct(
        public WorkflowService $workflowService,
        public SequenceService $sequenceService,
        public AuditService $auditService
    ) {}

    /**
     * Get the current reception queue state for a department.
     *
     * @return array{station: \App\Models\Station, current_calling: ?QueueTicket, waiting: \Illuminate\Database\Eloquent\Collection, waiting_count: int, called_count_today: int, on_hold: \Illuminate\Database\Eloquent\Collection}
     */
    public function getReceptionQueue(int $departmentId, ?int $stationId = null): array
    {
        $workflow = $this->workflowService->resolvePrimaryWorkflow($departmentId);
        $registrationStep = $this->workflowService->resolveRegistrationStep($workflow);
        $registrationStation = $registrationStep->station;

        if ($stationId && $stationId !== $registrationStation->id) {
            throw new InvalidStateException("Provided station #{$stationId} does not match active registration station #{$registrationStation->id}.");
        }

        $today = now()->format('Y-m-d');

        // Current calling ticket at this registration station
        $currentCalling = QueueTicket::query()
            ->with(['visit.patient', 'station', 'workflowStep'])
            ->where('station_id', $registrationStation->id)
            ->where('status', QueueTicketStatus::Called)
            ->latest('called_at')
            ->first();

        // Waiting tickets (ordered by priority desc, internal_sequence asc)
        $waitingTickets = QueueTicket::query()
            ->with(['visit.patient', 'station', 'workflowStep'])
            ->where('station_id', $registrationStation->id)
            ->where('status', QueueTicketStatus::Created)
            ->whereDate('created_at', $today)
            ->orderBy('priority', 'desc')
            ->orderBy('internal_sequence', 'asc')
            ->get();

        // On hold tickets (if any)
        $onHoldTickets = QueueTicket::query()
            ->with(['visit.patient', 'station', 'workflowStep'])
            ->where('station_id', $registrationStation->id)
            ->where('status', QueueTicketStatus::OnHold)
            ->whereDate('created_at', $today)
            ->get();

        $calledCountToday = QueueTicket::query()
            ->where('station_id', $registrationStation->id)
            ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::Completed, QueueTicketStatus::Skipped, QueueTicketStatus::NoShow])
            ->whereDate('created_at', $today)
            ->count();

        return [
            'station' => $registrationStation,
            'current_calling' => $currentCalling,
            'waiting' => $waitingTickets,
            'waiting_count' => $waitingTickets->count(),
            'called_count_today' => $calledCountToday,
            'on_hold' => $onHoldTickets,
        ];
    }

    /**
     * Call next queue ticket for reception at the resolved registration station.
     */
    public function callNext(int $departmentId, ?string $assignedTo = null, ?int $stationId = null): ?QueueTicket
    {
        $workflow = $this->workflowService->resolvePrimaryWorkflow($departmentId);
        $registrationStep = $this->workflowService->resolveRegistrationStep($workflow);
        $registrationStation = $registrationStep->station;

        if ($stationId && $stationId !== $registrationStation->id) {
            throw new InvalidStateException("Provided station #{$stationId} does not match active registration station #{$registrationStation->id}.");
        }

        return DB::transaction(function () use ($registrationStation, $assignedTo): ?QueueTicket {
            /** @var QueueTicket|null $ticket */
            $ticket = QueueTicket::query()
                ->where('station_id', $registrationStation->id)
                ->where('status', QueueTicketStatus::Created)
                ->orderBy('priority', 'desc')
                ->orderBy('internal_sequence', 'asc')
                ->lockForUpdate()
                ->first();

            if (! $ticket) {
                return null;
            }

            $ticket->update([
                'status' => QueueTicketStatus::Called,
                'called_at' => now(),
                'assigned_to' => $assignedTo,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Called,
                'from_status' => QueueTicketStatus::Created->value,
                'to_status' => QueueTicketStatus::Called->value,
                'user_id' => $assignedTo,
            ]);

            $this->auditService->log(
                action: 'RECEPTION_CALL_NEXT',
                entityType: 'QueueTicket',
                entityId: $ticket->id,
                metadata: [
                    'queue_number' => $ticket->queue_number,
                    'station_id' => $registrationStation->id,
                    'assigned_to' => $assignedTo,
                ],
                userId: $assignedTo
            );

            return $ticket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Recall a previously called reception ticket (e.g. patient did not hear).
     */
    public function recall(int $ticketId, ?string $assignedTo = null): QueueTicket
    {
        return DB::transaction(function () use ($ticketId, $assignedTo): QueueTicket {
            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            if ($ticket->status !== QueueTicketStatus::Called) {
                throw new InvalidStateException("Ticket #{$ticket->queue_number} cannot be recalled from status '{$ticket->status->value}'. Must be CALLED.");
            }

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Recalled,
                'from_status' => QueueTicketStatus::Called->value,
                'to_status' => QueueTicketStatus::Called->value,
                'user_id' => $assignedTo,
                'payload' => ['reason' => 'Reception recall announced'],
            ]);

            $this->auditService->log(
                action: 'RECEPTION_RECALL',
                entityType: 'QueueTicket',
                entityId: $ticket->id,
                metadata: [
                    'queue_number' => $ticket->queue_number,
                    'station_id' => $ticket->station_id,
                ],
                userId: $assignedTo
            );

            return $ticket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Skip a reception ticket (patient not present, defer).
     */
    public function skip(int $ticketId, ?string $reason = null, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticketId, $reason, $userId): QueueTicket {
            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            if ($ticket->status !== QueueTicketStatus::Called) {
                throw new InvalidStateException("Reception ticket #{$ticket->queue_number} cannot be skipped from status '{$ticket->status->value}'. Must be CALLED.");
            }

            $ticket->update([
                'status' => QueueTicketStatus::Skipped,
                'notes' => $reason,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::Skipped,
                'from_status' => QueueTicketStatus::Called->value,
                'to_status' => QueueTicketStatus::Skipped->value,
                'user_id' => $userId,
                'payload' => ['reason' => $reason],
            ]);

            $this->auditService->log(
                action: 'RECEPTION_TICKET_SKIPPED',
                entityType: 'QueueTicket',
                entityId: $ticket->id,
                metadata: ['reason' => $reason],
                userId: $userId
            );

            return $ticket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Mark a reception ticket as no-show.
     */
    public function noShow(int $ticketId, ?string $userId = null): QueueTicket
    {
        return DB::transaction(function () use ($ticketId, $userId): QueueTicket {
            /** @var QueueTicket $ticket */
            $ticket = QueueTicket::query()
                ->lockForUpdate()
                ->findOrFail($ticketId);

            if ($ticket->status !== QueueTicketStatus::Called) {
                throw new InvalidStateException("Reception ticket cannot be marked no-show from status '{$ticket->status->value}'. Must be CALLED.");
            }

            $ticket->update([
                'status' => QueueTicketStatus::NoShow,
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $ticket->id,
                'event_type' => QueueEventType::NoShow,
                'from_status' => QueueTicketStatus::Called->value,
                'to_status' => QueueTicketStatus::NoShow->value,
                'user_id' => $userId,
            ]);

            $this->auditService->log(
                action: 'RECEPTION_TICKET_NO_SHOW',
                entityType: 'QueueTicket',
                entityId: $ticket->id,
                metadata: [],
                userId: $userId
            );

            return $ticket->load(['visit.patient', 'station', 'workflowStep']);
        });
    }

    /**
     * Complete registration process atomically.
     *
     * @param  array<string, mixed>|null  $patientData
     * @return array{visit: Visit, acquisition: QueueAcquisition, registration_ticket: QueueTicket, next_ticket: ?QueueTicket}
     */
    public function completeRegistration(
        int $acquisitionId,
        ?int $patientId = null,
        ?array $patientData = null,
        ?int $priority = null,
        ?string $registeredBy = null
    ): array {
        return DB::transaction(function () use ($acquisitionId, $patientId, $patientData, $priority, $registeredBy): array {
            /** @var QueueAcquisition|null $acquisition */
            $acquisition = QueueAcquisition::query()
                ->with(['visit.workflow', 'visit.patient'])
                ->lockForUpdate()
                ->find($acquisitionId);

            if (! $acquisition) {
                throw new InvalidStateException("Queue acquisition #{$acquisitionId} not found.");
            }

            if ($acquisition->status === QueueAcquisitionStatus::Registered) {
                throw new BusinessConflictException("Registration already completed for acquisition #{$acquisitionId}.");
            }

            if ($acquisition->status === QueueAcquisitionStatus::Cancelled) {
                throw new InvalidStateException("Acquisition #{$acquisitionId} has been cancelled.");
            }

            /** @var Visit $visit */
            $visit = $acquisition->visit;

            // Resolve or link patient
            $resolvedPatient = null;
            if ($patientId) {
                $resolvedPatient = Patient::query()->findOrFail($patientId);
            } elseif ($patientData) {
                $nik = $patientData['nik'] ?? null;
                if ($nik) {
                    $existing = Patient::query()->where('nik', $nik)->first();
                    if ($existing) {
                        throw new BusinessConflictException("NIK '{$nik}' already exists for patient '{$existing->name}' (MRN: {$existing->mrn}). Link existing patient or use different NIK.");
                    }
                }
                $mrn = $patientData['mrn'] ?? ('MRN-' . strtoupper(Str::random(8)));
                $resolvedPatient = Patient::query()->create([
                    'mrn' => $mrn,
                    'nik' => $patientData['nik'] ?? null,
                    'name' => $patientData['name'],
                    'date_of_birth' => $patientData['date_of_birth'] ?? null,
                    'gender' => $patientData['gender'] ?? null,
                    'phone' => $patientData['phone'] ?? null,
                    'email' => $patientData['email'] ?? null,
                    'address' => $patientData['address'] ?? null,
                ]);
            } elseif ($visit->patient_id) {
                $resolvedPatient = $visit->patient;
            }

            if (! $resolvedPatient) {
                throw new InvalidStateException('Patient identification is required to complete registration.');
            }

            $updateData = [
                'patient_id' => $resolvedPatient->id,
                'registered_by' => $registeredBy,
                'registered_at' => now(),
            ];

            if ($priority) {
                $newPriority = Priority::tryFrom($priority);
                if ($newPriority) {
                    $updateData['priority'] = $newPriority;
                }
            }

            $visit->update($updateData);

            // Complete registration QueueTicket
            /** @var QueueTicket|null $regTicket */
            $regTicket = QueueTicket::query()
                ->where('visit_id', $visit->id)
                ->whereIn('status', [QueueTicketStatus::Created, QueueTicketStatus::Called, QueueTicketStatus::InProgress])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if (! $regTicket) {
                throw new InvalidStateException("Active registration ticket not found for visit #{$visit->id}.");
            }

            $prevStatus = $regTicket->status;
            $regTicket->update([
                'status' => QueueTicketStatus::Completed,
                'completed_at' => now(),
            ]);

            QueueEvent::query()->create([
                'queue_ticket_id' => $regTicket->id,
                'event_type' => QueueEventType::Completed,
                'from_status' => $prevStatus->value,
                'to_status' => QueueTicketStatus::Completed->value,
                'user_id' => $registeredBy,
                'payload' => ['reason' => 'Reception registration completed'],
            ]);

            // Update QueueAcquisition
            $acquisition->update([
                'status' => QueueAcquisitionStatus::Registered,
                'registered_by' => $registeredBy,
            ]);

            // Resolve next workflow step
            $workflow = $visit->workflow;
            $nextStep = $this->workflowService->resolveNextStep($workflow, $regTicket->workflowStep->sequence);

            $nextTicket = null;
            if ($nextStep) {
                $nextStation = $nextStep->station;
                $numbering = $this->sequenceService->generateQueueNumber($nextStation);

                /** @var QueueTicket $nextTicket */
                $nextTicket = QueueTicket::query()->create([
                    'visit_id' => $visit->id,
                    'workflow_step_id' => $nextStep->id,
                    'station_id' => $nextStation->id,
                    'queue_number' => $numbering['queue_number'],
                    'priority' => $visit->priority,
                    'internal_sequence' => $numbering['internal_sequence'],
                    'status' => QueueTicketStatus::Created,
                ]);

                QueueEvent::query()->create([
                    'queue_ticket_id' => $nextTicket->id,
                    'event_type' => QueueEventType::Created,
                    'from_status' => null,
                    'to_status' => QueueTicketStatus::Created->value,
                    'user_id' => $registeredBy,
                    'payload' => ['reason' => 'Clinical handoff after registration'],
                ]);
            } else {
                // No next step in workflow -> visit completed
                $visit->update([
                    'status' => VisitStatus::Completed,
                    'completed_at' => now(),
                ]);
            }

            $this->auditService->log(
                action: 'REGISTRATION_COMPLETED',
                entityType: 'QueueAcquisition',
                entityId: $acquisition->id,
                metadata: [
                    'visit_id' => $visit->id,
                    'patient_id' => $resolvedPatient->id,
                    'next_ticket_id' => $nextTicket?->id,
                    'next_station_id' => $nextTicket?->station_id,
                ],
                userId: $registeredBy
            );

            return [
                'visit' => $visit->fresh(['patient', 'department', 'workflow']),
                'acquisition' => $acquisition->fresh(),
                'registration_ticket' => $regTicket->fresh(['station', 'workflowStep']),
                'next_ticket' => $nextTicket?->load(['station', 'workflowStep']),
            ];
        });
    }
}
