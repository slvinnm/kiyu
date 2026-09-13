<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceptionRegisterQueueAcquisitionRequest;
use App\Http\Requests\Api\ReceptionVisitRequest;
use App\Http\Resources\QueueAcquisitionResource;
use App\Http\Resources\VisitResource;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Services\QueueAcquisitionService;
use App\Services\RegisterReceptionVisit;
use Illuminate\Http\JsonResponse;

class ReceptionController extends Controller
{
    public function __construct(
        private QueueAcquisitionService $queueAcquisitionService,
        private RegisterReceptionVisit $registerReceptionVisit,
    ) {}

    public function store(ReceptionVisitRequest $request): JsonResponse
    {
        $visit = $this->registerReceptionVisit->handle(
            data: $request->validated(),
            registeredBy: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Walk-in visit registered successfully.',
            'data' => new VisitResource($visit->load([
                'department',
                'queueTickets.station',
                'queueTickets.visitWorkflowStep.workflowStep',
            ])),
        ], 201);
    }

    public function registerQueueAcquisition(
        ReceptionRegisterQueueAcquisitionRequest $request,
        QueueAcquisition $queueAcquisition,
    ): JsonResponse {
        $patient = Patient::query()->findOrFail($request->integer('patient_id'));

        $acquisition = $this->queueAcquisitionService->registerPatient(
            acquisition: $queueAcquisition,
            patient: $patient,
            registeredBy: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Queue acquisition registered successfully.',
            'data' => new QueueAcquisitionResource($acquisition),
        ]);
    }
}
