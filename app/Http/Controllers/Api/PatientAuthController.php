<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PatientLoginRequest;
use App\Http\Requests\Api\PatientRegisterRequest;
use App\Http\Resources\PatientResource;
use App\Services\PatientAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public function me(Request $request): JsonResponse
    {
        abort_unless($request->user()->role === UserRole::PATIENT, 403);

        $patient = $request->user()->patient;
        abort_unless($patient, 403, 'Authenticated user has no patient profile.');

        return response()->json([
            'success' => true,
            'message' => 'Patient profile retrieved successfully.',
            'data' => new PatientResource($patient),
        ]);
    }
}
