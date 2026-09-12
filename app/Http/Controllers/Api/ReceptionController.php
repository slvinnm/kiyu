<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceptionRegisterQueueAcquisitionRequest;
use App\Http\Resources\QueueAcquisitionResource;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Services\QueueAcquisitionService;
use Illuminate\Http\JsonResponse;

class ReceptionController extends Controller
{
    public function __construct(
        private QueueAcquisitionService $queueAcquisitionService,
    ) {}

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
