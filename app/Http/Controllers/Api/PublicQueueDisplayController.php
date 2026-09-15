<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicQueueDisplayResource;
use App\Services\PublicQueueDisplayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicQueueDisplayController extends Controller
{
    public function __construct(private PublicQueueDisplayService $publicQueueDisplayService) {}

    public function show(Request $request, string $station): JsonResponse
    {
        $display = $this->publicQueueDisplayService->forStation(
            stationCode: $station,
            upcomingLimit: min($request->integer('limit', 5), 10),
        );

        return response()->json([
            'success' => true,
            'message' => 'Public queue display retrieved successfully.',
            'data' => new PublicQueueDisplayResource($display),
        ]);
    }
}
