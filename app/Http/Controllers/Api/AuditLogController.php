<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * List audit logs with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = AuditLog::query();

        if ($request->has('action')) {
            $query->where('action', $request->input('action'));
        }

        if ($request->has('entity_type')) {
            $query->where('entity_type', $request->input('entity_type'));
        }

        if ($request->has('entity_id')) {
            $query->where('entity_id', $request->input('entity_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        return response()->json([
            'data' => $query->latest('id')->paginate($request->input('per_page', 25)),
        ]);
    }
}
