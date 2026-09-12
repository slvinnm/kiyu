<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Enums\VisitWorkflowStepStatus;
use App\Models\QueueTicket;
use App\Models\VisitWorkflowStep;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QueueService
{
    /**
     * QueueService orchestrates queue operations.
     *
     * It uses:
     * - QueueStateMachine: for valid state transitions
     * - QueueSelector: for selecting the next ticket to call
     * - QueueNumberGenerator: for generating queue numbers (via WorkflowEngine)
     * - WorkflowEngine: for progressing workflow after completion
     *
     * All operations are transactional and concurrency-safe.
     */

    protected QueueStateMachine $stateMachine;
    protected QueueSelector $selector;
    protected WorkflowEngine $workflowEngine;

    public function __construct(
        ?QueueStateMachine $stateMachine = null,
        ?QueueSelector $selector = null,
        ?WorkflowEngine $workflowEngine = null
    ) {
        $this->stateMachine = $stateMachine ?? new QueueStateMachine();
        $this->selector = $selector ?? new QueueSelector();
        $this->workflowEngine = $workflowEngine ?? new WorkflowEngine();
    }

    /**
     * Call the next ticket for a station.
     *
     * Required invariant:
     *   Only CREATED tickets are selectable by callNext().
     *
     * @param int $stationId
     * @param int|null $calledByUserId Optional user ID who called the ticket
     * @return QueueTicket|null The called ticket, or null if no tickets waiting
     */
    public function callNext(int $stationId, ?int $calledByUserId = null): ?QueueTicket
    {
        return DB::transaction(function () use ($stationId, $calledByUserId) {
            $station = \App\Models\Station::findOrFail($stationId);

            // 1. Select next CREATED ticket (priority DESC, internal_sequence ASC)
            $ticket = $this->selector->callNext($station);

            if (! $ticket) {
                return null;
            }

            // 2. Transition CREATED → CALLED via state machine
            $this->stateMachine->apply($ticket, QueueStatus::CALLED, $calledByUserId);

            // 3. Return fresh ticket with updated status
            return $ticket->fresh();
        });
    }

    /**
     * Start working on a called ticket.
     *
     * Required transition:
     *   CALLED → IN_PROGRESS
     *
     * @param int $ticketId
     * @param int|null $startedByUserId Optional user ID who started the ticket
     * @return bool True if started, false if invalid transition
     */
    public function startTicket(int $ticketId, ?int $startedByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $startedByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate transition
            if (! $this->stateMachine->isEligibleToStart($ticket)) {
                return false;
            }

            // Apply transition
            return $this->stateMachine->apply($ticket, QueueStatus::IN_PROGRESS, $startedByUserId);
        });
    }

    /**
     * Complete a ticket and progress the workflow.
     *
     * Flow:
     *   QueueTicket.completed
     *   ↓
     *   VisitWorkflowStep.completed
     *   ↓
     *   WorkflowEngine.determineNextStep()
     *   ↓
     *   Create next VisitWorkflowStep
     *   ↓
     *   If requires_queue: create QueueTicket
     *   Else: continue to next non-queue step
     *   ↓
     *   If no steps remain: Visit = COMPLETED
     *
     * @param int $ticketId
     * @param int|null $completedByUserId Optional user ID who completed the ticket
     * @return array ['ticket' => QueueTicket, 'nextStep' => WorkflowStep|null, 'visitCompleted' => bool]
     */
    public function completeTicket(int $ticketId, ?int $completedByUserId = null): array
    {
        return DB::transaction(function () use ($ticketId, $completedByUserId) {
            // Lock the QueueTicket row to prevent concurrent completion/progression
            $ticket = QueueTicket::with([
                'visitWorkflowStep.visit', 'visitWorkflowStep.workflowStep.station'
            ])
                ->whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate that ticket is IN_PROGRESS
            if (! $this->stateMachine->isEligibleForCompletion($ticket)) {
                throw new ValidationException(['Ticket must be IN_PROGRESS to complete.']);
            }

            // 1. Mark ticket as completed
            $this->stateMachine->apply($ticket, QueueStatus::COMPLETED, $completedByUserId);

            // 2. Progress the workflow via WorkflowEngine
            $result = $this->workflowEngine->completeCurrentStep($ticket);

            // 3. Log the workflow progression if applicable
            if ($result['next_step'] !== null) {
                // The next step has been created; if it requires queue, a ticket was created
                // No additional logging needed here as WorkflowEngine handles it
            }

            return [
                'ticket' => $ticket->fresh(),
                'next_step' => $result['next_step'],
                'visit_completed' => $result['visit_completed'],
            ];
        });
    }

    /**
     * Hold a ticket (pause processing).
     *
     * Valid transitions:
     *   CALLED → ON_HOLD
     *   IN_PROGRESS → ON_HOLD
     *
     * @param int $ticketId
     * @param int|null $heldByUserId Optional user ID who placed on hold
     * @return bool True if held, false if invalid
     */
    public function holdTicket(int $ticketId, ?int $heldByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $heldByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($ticket->status->value, [
                    QueueStatus::CALLED->value,
                    QueueStatus::IN_PROGRESS->value,
                ])) {
                return false;
            }

            return $this->stateMachine->apply($ticket, QueueStatus::ON_HOLD, $heldByUserId);
        });
    }

    /**
     * Resume a held ticket.
     *
     * Required transition:
     *   ON_HOLD → CALLED
     *
     * @param int $ticketId
     * @param int|null $resumedByUserId Optional user ID who resumed the ticket
     * @return bool True if resumed, false if invalid
     */
    public function resumeTicket(int $ticketId, ?int $resumedByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $resumedByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            // Validate eligibility for resume
            if (! $this->stateMachine->isEligibleForResume($ticket)) {
                return false;
            }

            // Apply transition ON_HOLD → CALLED
            return $this->stateMachine->apply($ticket, QueueStatus::CALLED, $resumedByUserId);
        });
    }

    /**
     * Skip a ticket (optionally, based on workflow rules).
     *
     * Valid transitions:
     *   CREATED → SKIPPED
     *   CALLED → SKIPPED
     *   IN_PROGRESS → SKIPPED
     *   ON_HOLD → SKIPPED
     *
     * @param int $ticketId
     * @param int|null $skippedByUserId Optional user ID who skipped the ticket
     * @param string|null $reason Optional reason for skipping
     * @return bool True if skipped, false if invalid
     */
    public function skipTicket(int $ticketId, ?int $skippedByUserId = null, ?string $reason = null): bool
    {
        return DB::transaction(function () use ($ticketId, $skippedByUserId, $reason) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            // Check if skip is allowed by workflow step
            $workflowStep = $ticket->visitWorkflowStep->workflowStep;
            if (! $workflowStep->can_skip) {
                return false;
            }

            // Apply transition
            $result = $this->stateMachine->apply($ticket, QueueStatus::SKIPPED, $skippedByUserId);

            if ($result && $reason) {
                // Add skip reason to notes
                $ticket->update([
                    'notes' => $reason . (($ticket->notes) ? " | {$ticket->notes}" : ''),
                ]);
            }

            return $result;
        });
    }

    /**
     * Cancel a ticket.
     *
     * Valid transitions from any active state to CANCELLED.
     *
     * @param int $ticketId
     * @param int|null $cancelledByUserId Optional user ID who cancelled the ticket
     * @return bool True if cancelled, false if invalid
     */
    public function cancelTicket(int $ticketId, ?int $cancelledByUserId = null): bool
    {
        return DB::transaction(function () use ($ticketId, $cancelledByUserId) {
            $ticket = QueueTicket::whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            // Cannot cancel completed/skipped/etc. tickets
            if (! in_array($ticket->status->value, [
                    QueueStatus::CREATED->value,
                    QueueStatus::CALLED->value,
                    QueueStatus::IN_PROGRESS->value,
                    QueueStatus::ON_HOLD->value,
                ])) {
                return false;
            }

            return $this->stateMachine->apply($ticket, QueueStatus::CANCELLED, $cancelledByUserId);
        });
    }

    /**
     * Transfer a ticket to another valid service destination.
     *
     * Transfer is NOT workflow progression.
     * It means:
     *   Current queue/ticket
     *   ↓
     *   Transfer to another valid service destination (same workflow step)
     *   ↓
     *   Create new queue position
     *   ↓
     *   Patient joins destination queue
     *
     * @param int $ticketId
     * @param int $targetStationId ID of the station to transfer to
     * @param int|null $transferredByUserId Optional user ID who initiated transfer
     * @return array ['original' => QueueTicket, 'new' => QueueTicket]
     *
     * @throws \LogicException If transfer is invalid
     */
    public function transferTicket(int $ticketId, int $targetStationId, ?int $transferredByUserId = null): array
    {
        return DB::transaction(function () use ($ticketId, $targetStationId, $transferredByUserId) {
            $ticket = QueueTicket::with(['visitWorkflowStep.workflowStep', 'visit'])
                ->whereKey($ticketId)
                ->lockForUpdate()
                ->firstOrFail();

            $targetStation = \App\Models\Station::findOrFail($targetStationId);

            // Transfer only valid within the same workflow step
            $currentStep = $ticket->visitWorkflowStep->workflowStep;
            $targetStep = $targetStation->workflowSteps()
                ->where('workflow_version_id', $ticket->visit->workflow_version_id)
                ->where('name', $currentStep->name)
                ->first();

            if (! $targetStep) {
                throw new \LogicException("Cannot transfer to station {$targetStation->code}: no matching workflow step '{$currentStep->name}'.");
            }

            // 1. Mark original ticket as transferred
            $this->stateMachine->apply($ticket, QueueStatus::TRANSFERRED, $transferredByUserId);

            // 2. Create new queue ticket at target station
            // Reuse the same visit_workflow_step (same workflow step execution)
            $allocation = (new QueueNumberGenerator())->allocate($targetStation);
            $newTicket = QueueTicket::create([
                'visit_id' => $ticket->visit_id,
                'visit_workflow_step_id' => $ticket->visit_workflow_step_id,
                'station_id' => $targetStation->id,
                'queue_number' => $allocation['queue_number'],
                'priority' => $ticket->priority->value,
                'internal_sequence' => $allocation['internal_sequence'],
                'status' => QueueStatus::CREATED->value,
                // notes can be copied if desired
                'notes' => $ticket->notes,
            ]);

            // 3. Log creation event on new ticket (original transition event handled by QueueStateMachine::apply)
            $newTicket->events()->create([
                'event_type' => \App\Enums\QueueEventType::CREATED,
                'from_status' => null,
                'to_status' => QueueStatus::CREATED->value,
                'user_id' => $transferredByUserId,
            ]);

            return [
                'original' => $ticket->fresh(),
                'new' => $newTicket->fresh(),
            ];
        });
    }
}