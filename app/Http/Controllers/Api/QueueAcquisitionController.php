<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IntakeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueAcquisitionController extends Controller
{
    public function __construct(
        public IntakeService $intakeService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'in:KIOSK,MANUAL,ONLINE'],
            'status' => ['nullable', 'string', 'in:ACQUIRED,REGISTERED,CANCELLED'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'date' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $result = $this->intakeService->getAcquisitions(
            filters: $validated,
            perPage: (int) ($validated['per_page'] ?? 20)
        );

        return response()->json([
            'message' => 'Queue acquisitions retrieved.',
            'data' => $result,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $acquisition = $this->intakeService->getAcquisition($id);

        return response()->json([
            'message' => 'Queue acquisition retrieved.',
            'data' => $acquisition,
        ]);
    }

    public function cancel(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'user_id' => ['nullable', 'string'],
        ]);

        $acquisition = $this->intakeService->cancelAcquisition(
            id: $id,
            reason: $validated['reason'] ?? null,
            userId: $validated['user_id'] ?? null
        );

        return response()->json([
            'message' => 'Queue acquisition cancelled.',
            'data' => $acquisition,
        ]);
    }
}
