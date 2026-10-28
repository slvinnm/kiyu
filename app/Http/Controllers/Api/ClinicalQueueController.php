<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use App\Services\ClinicalQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClinicalQueueController extends Controller
{
    public function __construct(
        public ClinicalQueueService $clinicalQueueService
    ) {}

    /**
     * Call next queue ticket for a clinical station.
     */
    public function callNext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'assigned_to' => ['nullable', 'string'],
        ]);

        $ticket = $this->clinicalQueueService->callNext(
            stationId: (int) $validated['station_id'],
            assignedTo: $validated['assigned_to'] ?? null
        );

        if (! $ticket) {
            return response()->json([
                'message' => 'No eligible tickets in queue for this station.',
                'data' => null,
            ]);
        }

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} called.",
            'data' => $ticket,
        ]);
    }

    /**
     * Recall a ticket announcement.
     */
    public function recall(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->recall($ticket, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} recalled for clinical station.",
            'data' => $result,
        ]);
    }

    /**
     * Start clinical service for a ticket.
     */
    public function start(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->start($ticket, $userId);

        return response()->json([
            'message' => "Service started for ticket {$result->queue_number}. Visit is now in progress.",
            'data' => $result,
        ]);
    }

    /**
     * Hold a ticket.
     */
    public function hold(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $notes = $request->input('notes');
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->hold($ticket, $notes, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} placed on hold.",
            'data' => $result,
        ]);
    }

    /**
     * Resume a ticket from hold back to CALLED.
     */
    public function resume(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->resume($ticket, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} resumed to CALLED status.",
            'data' => $result,
        ]);
    }

    /**
     * Complete a ticket and advance the workflow.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $notes = $request->input('notes');
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->complete($ticket, $notes, $userId);

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} completed.",
            'data' => $result,
        ]);
    }

    /**
     * Skip a ticket.
     */
    public function skip(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $notes = $request->input('notes');
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->skip($ticket, $notes, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} marked as skipped.",
            'data' => $result,
        ]);
    }

    /**
     * Mark a ticket as No-Show.
     */
    public function noShow(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $notes = $request->input('notes');
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->noShow($ticket, $notes, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} marked as no-show.",
            'data' => $result,
        ]);
    }

    /**
     * Cancel a ticket.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);
        $notes = $request->input('notes');
        $userId = $request->input('user_id');

        $result = $this->clinicalQueueService->cancelTicket($ticket, $notes, $userId);

        return response()->json([
            'message' => "Ticket {$result->queue_number} cancelled.",
            'data' => $result,
        ]);
    }

    /**
     * Transfer a ticket to another compatible station.
     */
    public function transfer(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'target_station_id' => ['required', 'integer', 'exists:stations,id'],
            'notes' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
        ]);

        $ticket = QueueTicket::query()->findOrFail($id);

        $result = $this->clinicalQueueService->transfer(
            ticket: $ticket,
            targetStationId: (int) $validated['target_station_id'],
            notes: $validated['notes'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} transferred successfully to new station.",
            'data' => $result,
        ]);
    }
}
