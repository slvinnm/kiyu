<?php

namespace App\Http\Controllers\Api;

use App\Enums\IntakeChannel;
use App\Enums\StationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceptionRegisterQueueAcquisitionRequest;
use App\Http\Requests\Api\ReceptionVisitRequest;
use App\Http\Resources\PatientResource;
use App\Http\Resources\QueueAcquisitionResource;
use App\Http\Resources\VisitResource;
use App\Models\Department;
use App\Models\Patient;
use App\Models\QueueAcquisition;
use App\Services\QueueAcquisitionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceptionController extends Controller
{
    public function __construct(private QueueAcquisitionService $queueAcquisitionService) {}

    public function departments(): JsonResponse
    {
        abort_unless(
            in_array(request()->user()->role, [UserRole::ADMIN, UserRole::RECEPTIONIST], true),
            403
        );

        $departments = Department::query()
            ->where('is_active', true)
            ->whereHas('workflows', fn ($query) => $query->where('is_active', true))
            ->whereHas('stations', function ($query): void {
                $query
                    ->where('type', StationType::REGISTRATION->value)
                    ->where('is_active', true);
            })
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return response()->json([
            'success' => true,
            'message' => 'Reception departments retrieved successfully.',
            'data' => $departments,
        ]);
    }

    public function store(ReceptionVisitRequest $request): JsonResponse
    {
        $acquisition = $this->queueAcquisitionService->createReception(
            data: $request->validated(),
            createdBy: $request->user(),
            channel: IntakeChannel::WALK_IN,
        );

        $visit = $acquisition->visit->load([
            'department',
            'queueTickets.station',
            'queueTickets.visitWorkflowStep.workflowStep',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Walk-in visit registered successfully.',
            'data' => new VisitResource($visit),
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

        $acquisition = $this->queueAcquisitionService->attachPatient(
            acquisition: $queueAcquisition,
            patient: $patient,
            registeredBy: $request->user(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Patient linked to queue acquisition successfully.',
            'data' => new QueueAcquisitionResource($acquisition),
        ]);
    }
}
