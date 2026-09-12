<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CreateReferralRequest;
use App\Http\Resources\ReferralResource;
use App\Models\Department;
use App\Models\Referral;
use App\Models\Visit;
use App\Services\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ReferralController extends Controller
{
    public function __construct(
        private ReferralService $referralService,
    ) {}

    public function store(
        CreateReferralRequest $request,
        Visit $visit,
    ): JsonResponse {
        $user = $request->user();

        abort_unless(
            $user->role === UserRole::ADMIN
                || in_array($user->role, [UserRole::DOCTOR, UserRole::NURSE], true),
            403,
        );

        $visit->load('department');

        abort_unless(
            $user->role === UserRole::ADMIN || $user->department_id === $visit->department_id,
            403,
        );

        $targetDepartment = Department::query()->findOrFail(
            $request->integer('target_department_id'),
        );

        $referral = $this->referralService->create(
            sourceVisitId: $visit->id,
            targetDepartmentId: $targetDepartment->id,
            referredByUserId: $user->id,
            reason: $request->input('reason'),
            priority: $request->filled('priority')
                ? \App\Enums\Priority::from($request->integer('priority'))
                : null,
        );

        return response()->json([
            'success' => true,
            'message' => 'Referral created successfully.',
            'data' => new ReferralResource($referral->load([
                'sourceVisit.department',
                'targetDepartment',
                'targetVisit.queueTickets.station',
                'targetVisit.queueTickets.visitWorkflowStep.workflowStep',
            ])),
        ], 201);
    }

    public function show(Referral $referral): JsonResponse
    {
        Gate::authorize('view', $referral);

        return response()->json([
            'success' => true,
            'message' => 'Referral retrieved successfully.',
            'data' => new ReferralResource($referral->load([
                'sourceVisit.department',
                'targetDepartment',
                'targetVisit.queueTickets.station',
                'targetVisit.queueTickets.visitWorkflowStep.workflowStep',
            ])),
        ]);
    }
}
