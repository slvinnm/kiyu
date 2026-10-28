<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntakeController extends Controller
{
    public function __construct(
        public IntakeService $intakeService
    ) {}

    /**
     * Create a Kiosk walk-in intake.
     */
    public function kiosk(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'priority' => ['nullable', 'integer', 'in:1,2,3'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->intakeService->createKioskIntake(
            departmentId: (int) $validated['department_id'],
            priority: (int) ($validated['priority'] ?? 1),
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'message' => 'Kiosk intake created successfully.',
            'data' => $result,
        ], 201);
    }

    /**
     * Create a Manual walk-in intake by reception.
     */
    public function manual(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'priority' => ['nullable', 'integer', 'in:1,2,3'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'registered_by' => ['nullable', 'string'],
        ]);

        $result = $this->intakeService->createManualIntake(
            departmentId: (int) $validated['department_id'],
            priority: (int) ($validated['priority'] ?? 1),
            idempotencyKey: $validated['idempotency_key'] ?? null,
            registeredBy: $validated['registered_by'] ?? null
        );

        return response()->json([
            'message' => 'Manual intake created successfully.',
            'data' => $result,
        ], 201);
    }

    /**
     * Create an online booking intake.
     */
    public function online(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'priority' => ['nullable', 'integer', 'in:1,2,3'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
        ]);

        $result = $this->intakeService->createOnlineBooking(
            patientId: (int) $validated['patient_id'],
            departmentId: (int) $validated['department_id'],
            priority: (int) ($validated['priority'] ?? 1),
            idempotencyKey: $validated['idempotency_key'] ?? null
        );

        return response()->json([
            'message' => 'Online booking created successfully. Patient must check in at kiosk upon arrival.',
            'data' => $result,
        ], 201);
    }

    /**
     * Perform self check-in for an online booking at kiosk.
     */
    public function onlineCheckin(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'booking_identifier' => ['required', 'string'],
        ]);

        $result = $this->intakeService->performOnlineCheckin(
            bookingIdentifier: $validated['booking_identifier']
        );

        return response()->json([
            'message' => 'Check-in successful. Patient has entered the reception queue.',
            'data' => $result,
        ]);
    }

    /**
     * Preview / lookup an online booking for kiosk display (no check-in performed).
     */
    public function onlineLookup(Request $request, string $identifier): JsonResponse
    {
        $result = $this->intakeService->lookupOnlineBooking(
            bookingIdentifier: $identifier
        );

        return response()->json([
            'message' => 'Online booking preview retrieved.',
            'data' => $result,
        ]);
    }
}
