<?php
namespace App\Http\Controllers\Api;
use App\Enums\Priority;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateVisitPriorityRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use App\Services\UpdateVisitPriority;
use Illuminate\Http\JsonResponse;
class VisitPriorityController extends Controller
{
    public function __construct(private UpdateVisitPriority $updateVisitPriority) {}
    public function update(UpdateVisitPriorityRequest $request, Visit $visit): JsonResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, [UserRole::ADMIN, UserRole::DOCTOR, UserRole::NURSE], true), 403);
        $visit = $this->updateVisitPriority->handle($visit, Priority::from($request->integer('priority')), $user);
        return response()->json(['success' => true, 'message' => 'Visit priority updated successfully.', 'data' => new VisitResource($visit->load(['department', 'queueTickets.station', 'queueTickets.visitWorkflowStep.workflowStep']))]);
    }
}
