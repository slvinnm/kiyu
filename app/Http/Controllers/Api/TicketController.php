<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QueueTicket;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TicketController extends Controller
{
    /**
     * List tickets.
     */
    public function index(Request $request): JsonResponse
    {
        $query = QueueTicket::query()->with(['station', 'workflowStep', 'visit.patient']);

        if ($request->has('station_id')) {
            $query->where('station_id', $request->input('station_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('visit_id')) {
            $query->where('visit_id', $request->input('visit_id'));
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }

        return response()->json([
            'data' => $query->latest('id')->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * Show ticket details.
     */
    public function show(int $id): JsonResponse
    {
        $ticket = QueueTicket::query()
            ->with(['station', 'workflowStep', 'visit.patient', 'events', 'transferredFrom', 'transferredToStation'])
            ->findOrFail($id);

        return response()->json([
            'data' => $ticket,
        ]);
    }

    /**
     * Get lifecycle events for a ticket.
     */
    public function events(int $id): JsonResponse
    {
        $ticket = QueueTicket::query()->findOrFail($id);

        return response()->json([
            'data' => $ticket->events()->orderBy('id')->get(),
        ]);
    }
}
