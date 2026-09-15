<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\QueueCompleteRequest;
use App\Http\Requests\Api\QueueSkipRequest;
use App\Http\Requests\Api\QueueTransferRequest;
use App\Http\Resources\QueueTicketResource;
use App\Http\Resources\StationResource;
use App\Http\Resources\WorkflowStepResource;
use App\Models\QueueTicket;
use App\Models\Station;
use App\Services\QueueService;
use Illuminate\Http\JsonResponse;

class QueueController extends Controller
{
    public function __construct(private QueueService $queueService) {}

    public function stations(): JsonResponse
    {
        $user = request()->user();

        abort_unless($user->role !== UserRole::PATIENT, 403);

        $query = Station::query()
            ->where('is_active', true)
            ->with('department')
            ->orderBy('name');

        if ($user->role !== UserRole::ADMIN) {
            abort_unless($user->station && $user->can('view', $user->station), 403);
            $query->whereKey($user->station_id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Stations retrieved successfully.',
            'data' => StationResource::collection($query->get()),
        ]);
    }

    public function callNext(Station $station): JsonResponse
    {
        request()->user()->can('callNext', $station) || abort(403);

        $ticket = $this->queueService->callNext(
            stationId: $station->id,
            calledByUserId: request()->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => $ticket
                ? 'Next queue ticket called successfully.'
                : 'No queue ticket is available.',
            'data' => $ticket ? new QueueTicketResource($ticket->load([
                'station',
                'visit',
                'visitWorkflowStep.workflowStep',
            ])) : null,
        ]);
    }

    public function start(QueueTicket $queueTicket): JsonResponse
    {
        request()->user()->can('manage', $queueTicket) || abort(403);

        $started = $this->queueService->startTicket(
            $queueTicket->id,
            request()->user()->id,
        );

        if (! $started) {
            return response()->json([
                'success' => false,
                'message' => 'Queue ticket cannot be started from its current state.',
                'data' => null,
            ], 422);
        }

        return $this->ticketResponse($queueTicket->fresh());
    }

    public function complete(
        QueueCompleteRequest $request,
        QueueTicket $queueTicket,
    ): JsonResponse {
        $request->user()->can('manage', $queueTicket) || abort(403);

        $result = $this->queueService->completeTicket(
            ticketId: $queueTicket->id,
            completedByUserId: $request->user()->id,
            completionContext: $request->input('completion_context', []),
        );

        return response()->json([
            'success' => true,
            'message' => 'Queue ticket completed successfully.',
            'data' => [
                'ticket' => new QueueTicketResource($result['ticket']->load([
                    'station',
                    'visit',
                    'visitWorkflowStep.workflowStep',
                ])),
                'next_step' => $result['next_step']
                    ? new WorkflowStepResource($result['next_step'])
                    : null,
                'visit_completed' => $result['visit_completed'],
                'repeated' => $result['repeated'],
            ],
        ]);
    }

    public function hold(QueueTicket $queueTicket): JsonResponse
    {
        request()->user()->can('manage', $queueTicket) || abort(403);

        $updated = $this->queueService->holdTicket(
            $queueTicket->id,
            request()->user()->id,
        );

        return $this->booleanResponse($updated, 'Queue ticket put on hold.', 'Queue ticket cannot be put on hold.');
    }

    public function resume(QueueTicket $queueTicket): JsonResponse
    {
        request()->user()->can('manage', $queueTicket) || abort(403);

        $updated = $this->queueService->resumeTicket(
            $queueTicket->id,
            request()->user()->id,
        );

        return $this->booleanResponse($updated, 'Queue ticket resumed successfully.', 'Queue ticket cannot be resumed.');
    }

    public function skip(
        QueueSkipRequest $request,
        QueueTicket $queueTicket,
    ): JsonResponse {
        $request->user()->can('manage', $queueTicket) || abort(403);

        $result = $this->queueService->skipTicket(
            ticketId: $queueTicket->id,
            skippedByUserId: $request->user()->id,
            reason: $request->input('reason'),
            context: $request->input('context', []),
        );

        if (! $result['skipped']) {
            return response()->json([
                'success' => false,
                'message' => 'Queue ticket cannot be skipped.',
                'data' => $result,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'Queue ticket skipped successfully.',
            'data' => [
                'ticket' => new QueueTicketResource($result['ticket']->load([
                    'station',
                    'visit',
                    'visitWorkflowStep.workflowStep',
                ])),
                'next_step' => $result['next_step']
                    ? new WorkflowStepResource($result['next_step'])
                    : null,
                'visit_completed' => $result['visit_completed'],
            ],
        ]);
    }

    public function noShow(QueueTicket $queueTicket): JsonResponse
    {
        request()->user()->can('manage', $queueTicket) || abort(403);

        $updated = $this->queueService->markNoShow(
            $queueTicket->id,
            request()->user()->id,
        );

        return $this->booleanResponse($updated, 'Queue ticket marked as no-show.', 'Queue ticket cannot be marked as no-show.');
    }

    public function cancel(QueueTicket $queueTicket): JsonResponse
    {
        request()->user()->can('manage', $queueTicket) || abort(403);

        $updated = $this->queueService->cancelTicket(
            $queueTicket->id,
            request()->user()->id,
        );

        return $this->booleanResponse($updated, 'Queue ticket cancelled successfully.', 'Queue ticket cannot be cancelled.');
    }

    public function transfer(
        QueueTransferRequest $request,
        QueueTicket $queueTicket,
    ): JsonResponse {
        $request->user()->can('manage', $queueTicket) || abort(403);

        $targetStation = Station::query()->findOrFail($request->integer('target_station_id'));
        $request->user()->can('view', $targetStation) || abort(403);

        $result = $this->queueService->transferTicket(
            ticketId: $queueTicket->id,
            targetStationId: $request->integer('target_station_id'),
            transferredByUserId: $request->user()->id,
        );

        return response()->json([
            'success' => true,
            'message' => 'Queue ticket transferred successfully.',
            'data' => [
                'original' => new QueueTicketResource($result['original']->load([
                    'station',
                    'visit',
                    'visitWorkflowStep.workflowStep',
                ])),
                'new' => new QueueTicketResource($result['new']->load([
                    'station',
                    'visit',
                    'visitWorkflowStep.workflowStep',
                ])),
            ],
        ]);
    }

    private function ticketResponse(QueueTicket $ticket): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Queue ticket updated successfully.',
            'data' => new QueueTicketResource($ticket->load([
                'station',
                'visit',
                'visitWorkflowStep.workflowStep',
            ])),
        ]);
    }

    private function booleanResponse(bool $updated, string $successMessage, string $errorMessage): JsonResponse
    {
        return response()->json([
            'success' => $updated,
            'message' => $updated ? $successMessage : $errorMessage,
            'data' => null,
        ], $updated ? 200 : 422);
    }
}
