<?php

namespace App\Http\Controllers\Api;

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use App\Enums\UserRole;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ReceptionCallNextRequest;
use App\Http\Requests\Api\ReceptionVisitRequest;
use App\Http\Resources\QueueAcquisitionResource;
use App\Http\Resources\QueueTicketResource;
use App\Models\Department;
use App\Models\QueueAcquisition;
use App\Services\QueueAcquisitionService;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueAcquisitionController extends Controller
{
    public function __construct(
        private QueueAcquisitionService $queueAcquisitionService,
        private QueueService $queueService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            in_array($user->role, [UserRole::ADMIN, UserRole::RECEPTIONIST], true),
            403
        );

        $departmentCode = trim($request->string('department_code')->toString());
        $search = trim($request->string('search')->toString());

        $query = QueueAcquisition::query()
            ->with([
                'department',
                'visit.patient',
                'visit.queueTickets.station',
                'visit.queueTickets.visitWorkflowStep.workflowStep',
            ])
            ->where('status', QueueAcquisitionStatus::ACQUIRED->value)
            ->whereHas('visit', function ($query): void {
                $query->whereIn('status', [
                    VisitStatus::WAITING->value,
                    VisitStatus::IN_PROGRESS->value,
                ]);
            })
            ->when($departmentCode !== '', function ($query) use ($departmentCode): void {
                $query->whereHas('department', function ($query) use ($departmentCode): void {
                    $query->where('code', $departmentCode);
                });
            })
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    if (is_numeric($search)) {
                        $query->orWhere('id', (int) $search);
                    }

                    $query
                        ->orWhereHas('visit', function ($query) use ($search): void {
                            $query
                                ->where('visit_number', 'like', "%{$search}%")
                                ->orWhereHas('patient', function ($query) use ($search): void {
                                    $query
                                        ->where('name', 'like', "%{$search}%")
                                        ->orWhere('medical_record_number', $search)
                                        ->orWhere('national_id', $search)
                                        ->orWhere('phone', $search);
                                });
                        })
                        ->orWhereHas('visit.queueTickets', function ($query) use ($search): void {
                            $query->where('queue_number', 'like', "%{$search}%");
                        });
                });
            })
            ->orderBy('acquired_at')
            ->limit(20);

        return response()->json([
            'success' => true,
            'message' => 'Queue acquisitions retrieved successfully.',
            'data' => QueueAcquisitionResource::collection($query->get()),
        ]);
    }

    public function store(ReceptionVisitRequest $request): JsonResponse
    {
        $acquisition = $this->queueAcquisitionService->createReception(
            data: $request->validated(),
            createdBy: $request->user(),
            channel: IntakeChannel::MANUAL,
        );

        return response()->json([
            'success' => true,
            'message' => 'Manual queue acquisition created successfully.',
            'data' => new QueueAcquisitionResource($acquisition),
        ], 201);
    }

    public function callNext(ReceptionCallNextRequest $request): JsonResponse
    {
        $department = Department::query()
            ->where('code', $request->string('department_code')->toString())
            ->where('is_active', true)
            ->firstOrFail();

        $ticket = $this->queueService->callNextForReception(
            departmentId: $department->id,
            calledByUserId: $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => $ticket
                ? 'Next registration queue called successfully.'
                : 'No registration queue acquisition is available.',
            'data' => $ticket
                ? [
                    'ticket' => new QueueTicketResource($ticket),
                    'acquisition' => new QueueAcquisitionResource($ticket->visit->queueAcquisition),
                ]
                : null,
        ]);
    }
}
