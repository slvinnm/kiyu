<?php

namespace App\Http\Controllers\Api;

use App\Enums\QueueAcquisitionStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\QueueAcquisitionResource;
use App\Models\QueueAcquisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QueueAcquisitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless(
            in_array($user->role, [
                UserRole::ADMIN,
                UserRole::RECEPTIONIST,
            ], true),
            403
        );

        $search = trim($request->string('search')->toString());

        $query = QueueAcquisition::query()
            ->with([
                'department',
                'visit.queueTickets',
            ])
            ->where('status', QueueAcquisitionStatus::ACQUIRED)
            ->orderBy('acquired_at');

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('id', is_numeric($search) ? (int) $search : 0)
                    ->orWhereHas('visit', function ($query) use ($search): void {
                        $query->where('visit_number', 'like', "%{$search}%");
                    })
                    ->orWhereHas('visit.queueTickets', function ($query) use ($search): void {
                        $query->where('queue_number', 'like', "%{$search}%");
                    });
            });
        }

        $acquisitions = $query
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Queue acquisitions retrieved successfully.',
            'data' => QueueAcquisitionResource::collection($acquisitions),
        ]);
    }
}
