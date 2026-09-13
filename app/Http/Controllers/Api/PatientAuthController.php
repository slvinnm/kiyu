<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientAccessRequest;
use App\Http\Requests\Api\PatientLoginRequest;
use App\Http\Requests\Api\PatientRegisterRequest;
use App\Http\Resources\PatientResource;
use App\Services\PatientAuthService;
use Illuminate\Http\JsonResponse;

class PatientAuthController extends Controller
{
    public function __construct(
        private PatientAuthService $patientAuthService,
    ) {}

    public function register(PatientRegisterRequest $request): JsonResponse
    {
        $result = $this->patientAuthService->register($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Patient account registered successfully.',
            'data' => [
                'token' => $result['token'],
                'patient' => new PatientResource($result['patient']),
            ],
        ], 201);
    }

    public function login(PatientLoginRequest $request): JsonResponse
    {
        $result = $this->patientAuthService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Patient login successful.',
            'data' => [
                'token' => $result['token'],
                'patient' => new PatientResource($result['patient']),
            ],
        ]);
    }

    public function me(PatientAccessRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Patient profile retrieved successfully.',
            'data' => new PatientResource($request->user()->patient),
        ]);
    }
}
