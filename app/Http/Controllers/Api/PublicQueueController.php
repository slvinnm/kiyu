<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueTicketStatus;
use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQueueController extends Controller
{
    /**
     * Display public queue status without revealing any PII.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Station::query()
            ->where('is_active', true)
            ->with(['department:id,name,code']);

        if ($request->has('station_id')) {
            $query->where('id', $request->input('station_id'));
        }

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        $stations = $query->get()->map(function (Station $station): array {
            // Get currently active ticket (CALLED or IN_PROGRESS)
            $currentTicket = $station->queueTickets()
                ->whereIn('status', [QueueTicketStatus::Called, QueueTicketStatus::InProgress])
                ->latest('updated_at')
                ->select(['id', 'station_id', 'queue_number', 'status', 'called_at', 'started_at'])
                ->first();

            // Get count of waiting tickets
            $waitingCount = $station->queueTickets()
                ->where('status', QueueTicketStatus::Created)
                ->count();

            // Next 5 upcoming tickets (scope to station and today for clean numbering)
            $today = now()->format('Y-m-d');
            $upcomingTickets = $station->queueTickets()
                ->where('status', QueueTicketStatus::Created)
                ->whereDate('created_at', $today)
                ->orderBy('priority', 'desc')
                ->orderBy('internal_sequence', 'asc')
                ->limit(5)
                ->pluck('queue_number');

            return [
                'station_id' => $station->id,
                'station_name' => $station->name,
                'station_code' => $station->code,
                'station_type' => $station->type->value,
                'department_name' => $station->department?->name,
                'current_calling' => $currentTicket ? [
                    'queue_number' => $currentTicket->queue_number,
                    'status' => $currentTicket->status->value,
                ] : null,
                'waiting_count' => $waitingCount,
                'upcoming_queue_numbers' => $upcomingTickets,
            ];
        });

        return response()->json([
            'data' => $stations,
        ]);
    }
}
