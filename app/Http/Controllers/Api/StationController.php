<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueTicketStatus;
use App\Enums\StationType;
use App\Http\Controllers\Controller;
use App\Models\Station;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StationController extends Controller
{
    /**
     * List all stations.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Station::query()->with('department');

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a station.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:stations,code'],
            'type' => ['required', Rule::enum(StationType::class)],
            'queue_prefix' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $station = Station::query()->create([
            'department_id' => $validated['department_id'],
            'name' => $validated['name'],
            'code' => $validated['code'],
            'type' => $validated['type'],
            'queue_prefix' => $validated['queue_prefix'] ?? 'A',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Station created successfully.',
            'data' => $station->load('department'),
        ], 201);
    }

    /**
     * Show a station.
     */
    public function show(int $id): JsonResponse
    {
        $station = Station::query()->with('department')->findOrFail($id);

        return response()->json([
            'data' => $station,
        ]);
    }

    /**
     * Update a station.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $station = Station::query()->findOrFail($id);

        $validated = $request->validate([
            'department_id' => ['sometimes', 'required', 'integer', 'exists:departments,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:stations,code,' . $id],
            'type' => ['sometimes', 'required', Rule::enum(StationType::class)],
            'queue_prefix' => ['nullable', 'string', 'max:10'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $station->update($validated);

        return response()->json([
            'message' => 'Station updated successfully.',
            'data' => $station->load('department'),
        ]);
    }

    /**
     * View the operational queue for this station.
     */
    public function queue(int $id): JsonResponse
    {
        $station = Station::query()->findOrFail($id);

        $tickets = $station->queueTickets()
            ->with(['visit.patient', 'workflowStep'])
            ->whereIn('status', [
                QueueTicketStatus::Created,
                QueueTicketStatus::Called,
                QueueTicketStatus::InProgress,
                QueueTicketStatus::OnHold,
            ])
            ->orderByRaw("CASE 
                WHEN status = 'IN_PROGRESS' THEN 1 
                WHEN status = 'CALLED' THEN 2 
                WHEN status = 'CREATED' THEN 3 
                ELSE 4 END")
            ->orderBy('priority', 'desc')
            ->orderBy('internal_sequence', 'asc')
            ->get();

        return response()->json([
            'station' => $station,
            'data' => $tickets,
        ]);
    }
}
