<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\OnlineVisitRequest;
use App\Http\Resources\VisitResource;
use App\Services\CreateOnlineVisit;
use Illuminate\Http\JsonResponse;

class OnlineController extends Controller
{
    public function __construct(
        private CreateOnlineVisit $createOnlineVisit,
    ) {}

    public function store(OnlineVisitRequest $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::PATIENT, 403);

        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'Authenticated user has no patient profile.');

        $visit = $this->createOnlineVisit->handle(
            patient: $patient,
            departmentCode: $request->string('department_code')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Online visit registered successfully.',
            'data' => new VisitResource($visit->load([
                'department',
                'queueTickets.station',
                'queueTickets.visitWorkflowStep.workflowStep',
            ])),
        ], 201);
    }
}
