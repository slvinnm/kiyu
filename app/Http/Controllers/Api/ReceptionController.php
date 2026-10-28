<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReceptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    public function __construct(
        public ReceptionService $receptionService
    ) {}

    /**
     * Call next queue ticket for reception at the resolved registration station.
     */
    public function callNext(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'station_id' => ['nullable', 'integer', 'exists:stations,id'],
            'assigned_to' => ['nullable', 'string'],
        ]);

        $ticket = $this->receptionService->callNext(
            departmentId: (int) $validated['department_id'],
            assignedTo: $validated['assigned_to'] ?? null,
            stationId: isset($validated['station_id']) ? (int) $validated['station_id'] : null
        );

        if (! $ticket) {
            return response()->json([
                'message' => 'No eligible tickets in reception queue.',
                'data' => null,
            ]);
        }

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} called for reception.",
            'data' => $ticket,
        ]);
    }

    /**
     * Complete patient registration.
     */
    public function completeRegistration(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'acquisition_id' => ['required', 'integer', 'exists:queue_acquisitions,id'],
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'patient_data' => ['nullable', 'array'],
            'patient_data.name' => ['required_with:patient_data', 'string', 'max:255'],
            'patient_data.nik' => ['nullable', 'string', 'max:50'],
            'patient_data.mrn' => ['nullable', 'string', 'max:50'],
            'patient_data.date_of_birth' => ['nullable', 'date'],
            'patient_data.gender' => ['nullable', 'string'],
            'patient_data.phone' => ['nullable', 'string'],
            'patient_data.email' => ['nullable', 'email'],
            'patient_data.address' => ['nullable', 'string'],
            'priority' => ['nullable', 'integer', 'in:1,2,3'],
            'registered_by' => ['nullable', 'string'],
        ]);

        $result = $this->receptionService->completeRegistration(
            acquisitionId: (int) $validated['acquisition_id'],
            patientId: isset($validated['patient_id']) ? (int) $validated['patient_id'] : null,
            patientData: $validated['patient_data'] ?? null,
            priority: isset($validated['priority']) ? (int) $validated['priority'] : null,
            registeredBy: $validated['registered_by'] ?? null
        );

        return response()->json([
            'message' => 'Registration completed successfully and clinical ticket created.',
            'data' => $result,
        ]);
    }

    /**
     * Recall a reception ticket (announce again).
     */
    public function recall(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'assigned_to' => ['nullable', 'string'],
        ]);

        $ticket = $this->receptionService->recall(
            ticketId: $id,
            assignedTo: $validated['assigned_to'] ?? null
        );

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} recalled for reception.",
            'data' => $ticket,
        ]);
    }

    /**
     * Skip a reception ticket.
     */
    public function skip(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
        ]);

        $ticket = $this->receptionService->skip(
            ticketId: $id,
            reason: $validated['reason'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} skipped.",
            'data' => $ticket,
        ]);
    }

    /**
     * Mark a reception ticket as no-show.
     */
    public function noShow(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'string'],
        ]);

        $ticket = $this->receptionService->noShow(
            ticketId: $id,
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => "Ticket {$ticket->queue_number} marked no-show.",
            'data' => $ticket,
        ]);
    }
}
