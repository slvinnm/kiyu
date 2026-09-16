<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AuthAccessRequest;
use App\Http\Requests\Api\AuthLoginRequest;
use App\Http\Requests\Api\PatientRegisterRequest;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function __construct(private AuthService $authService) {}

    public function register(PatientRegisterRequest $request): JsonResponse
    {
        $result = $this->authService->registerPatient($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Patient account registered successfully.',
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
                'profile' => new UserResource($result['user']),
            ],
        ], 201);
    }

    public function login(AuthLoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->string('email')->toString(),
            $request->string('password')->toString(),
        );

        return response()->json([
            'success' => true,
            'message' => 'Login successful.',
            'data' => [
                'token' => $result['token'],
                'user' => new UserResource($result['user']),
            ],
        ]);
    }

    public function me(AuthAccessRequest $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'Authenticated user retrieved successfully.',
            'data' => new UserResource($request->user()->load('patient')),
        ]);
    }

    public function logout(AuthAccessRequest $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout successful.',
        ]);
    }
}
