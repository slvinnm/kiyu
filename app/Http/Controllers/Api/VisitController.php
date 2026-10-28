<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Visit;
use App\Services\VisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    public function __construct(
        public VisitService $visitService
    ) {}

    /**
     * List visits with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Visit::query()->with(['patient', 'department', 'workflow']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('patient_id')) {
            $query->where('patient_id', $request->input('patient_id'));
        }

        if ($request->has('intake_channel')) {
            $query->where('intake_channel', $request->input('intake_channel'));
        }

        if ($request->has('date')) {
            $query->whereDate('created_at', $request->input('date'));
        }

        return response()->json([
            'data' => $query->latest('id')->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * Show a visit and all its details (tickets, events, acquisitions, patient).
     */
    public function show(int $id): JsonResponse
    {
        $visit = Visit::query()
            ->with([
                'patient',
                'department',
                'workflow',
                'queueAcquisitions',
                'queueTickets.station',
                'queueTickets.workflowStep',
                'queueTickets.events',
                'outgoingReferrals.targetDepartment',
            ])
            ->findOrFail($id);

        return response()->json([
            'data' => $visit,
        ]);
    }

    /**
     * Update visit priority and propagate to active tickets.
     */
    public function updatePriority(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'priority' => ['required', 'integer', 'in:1,2,3'],
            'user_id' => ['nullable', 'string'],
        ]);

        $visit = Visit::query()->findOrFail($id);
        $updatedVisit = $this->visitService->updatePriority(
            visit: $visit,
            priority: (int) $validated['priority'],
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => "Priority updated to {$updatedVisit->priority->label()} and propagated to active tickets.",
            'data' => $updatedVisit,
        ]);
    }

    /**
     * Cancel a visit.
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
        ]);

        $visit = Visit::query()->findOrFail($id);
        $cancelledVisit = $this->visitService->cancelVisit(
            visit: $visit,
            reason: $validated['reason'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => 'Visit cancelled successfully.',
            'data' => $cancelledVisit,
        ]);
    }
}
