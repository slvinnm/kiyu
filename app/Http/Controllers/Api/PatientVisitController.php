<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientAccessRequest;
use App\Http\Resources\PatientQueueTicketResource;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Services\CheckInOnlineVisit;
use App\Services\PatientQueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientVisitController extends Controller
{
    public function __construct(
        private CheckInOnlineVisit $checkInOnlineVisit,
        private PatientQueueService $patientQueueService,
    ) {}

    public function index(PatientAccessRequest $request): JsonResponse
    {
        $patient = $request->user()->patient;

        $visits = Visit::query()
            ->where('patient_id', $patient->id)
            ->with([
                'department',
                'queueTickets.station',
                'queueTickets.visitWorkflowStep.workflowStep',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => VisitResource::collection($visits),
        ]);
    }

    public function show(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('view', $visit);

        $visit->load([
            'department',
            'queueTickets.station',
            'queueTickets.visitWorkflowStep.workflowStep',
        ]);

        return response()->json([
            'success' => true,
            'data' => new VisitResource($visit),
        ]);
    }

    public function queue(PatientAccessRequest $request): JsonResponse
    {
        $patient = $request->user()->patient;

        $tickets = $this->patientQueueService->activeTickets($patient);

        return response()->json([
            'success' => true,
            'data' => PatientQueueTicketResource::collection($tickets),
        ]);
    }

    public function checkIn(Request $request, Visit $visit): JsonResponse
    {
        $this->authorize('checkIn', $visit);

        $visit = $this->checkInOnlineVisit->handle($visit);

        return response()->json([
            'success' => true,
            'message' => 'Visit checked in successfully.',
            'data' => new VisitResource($visit),
        ]);
    }
}
