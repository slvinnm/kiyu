<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceptionRegisterQueueAcquisitionRequest;
use App\Http\Requests\Api\ReceptionVisitRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\QueueAcquisitionResource;
use App\Http\Resources\VisitResource;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Services\QueueAcquisitionService;
use App\Services\RegisterReceptionVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function patients(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [UserRole::ADMIN, UserRole::RECEPTIONIST], true), 403);

        $search = trim($request->string('search')->toString());

        $patients = Patient::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('medical_record_number', $search)
                        ->orWhere('national_id', $search)
                        ->orWhere('phone', $search);
                });
            })
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Patients retrieved successfully.',
            'data' => PatientResource::collection($patients),
        ]);
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
