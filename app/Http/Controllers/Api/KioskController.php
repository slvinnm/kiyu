<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AcquireQueueRequest;
use App\Http\Resources\QueueAcquisitionResource;
use App\Models\Department;
use App\Services\QueueAcquisitionService;
use Illuminate\Http\JsonResponse;

class KioskController extends Controller
{
    public function __construct(
        private QueueAcquisitionService $queueAcquisitionService,
    ) {}

    public function departments(): JsonResponse
    {
        $departments = Department::query()
            ->where('is_active', true)
            ->whereHas('workflows', fn ($query) => $query->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Departments retrieved successfully.',
            'data' => $departments,
        ]);
    }

    public function acquire(AcquireQueueRequest $request): JsonResponse
    {
        $acquisition = $this->queueAcquisitionService->acquire(
            $request->string('department_code')->toString(),
            $request->input('idempotency_key'),
        );

        return response()->json([
            'success' => true,
            'message' => 'Queue acquired successfully.',
            'data' => new QueueAcquisitionResource($acquisition),
        ], $acquisition->wasRecentlyCreated ? 201 : 200);
    }
}
