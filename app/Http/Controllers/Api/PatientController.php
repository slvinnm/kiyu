<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PatientController extends Controller
{
    /**
     * Search and list patients.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Patient::query();

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mrn', 'like', "%{$search}%")
                    ->orWhere('nik', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('nik')) {
            $query->where('nik', $request->input('nik'));
        }

        if ($request->has('mrn')) {
            $query->where('mrn', $request->input('mrn'));
        }

        return response()->json([
            'data' => $query->latest('id')->paginate($request->input('per_page', 20)),
        ]);
    }

    /**
     * Create a patient.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50', 'unique:patients,nik'],
            'mrn' => ['nullable', 'string', 'max:50', 'unique:patients,mrn'],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $mrn = $validated['mrn'] ?? ('MRN-' . strtoupper(Str::random(8)));

        $patient = Patient::query()->create([
            'name' => $validated['name'],
            'nik' => $validated['nik'] ?? null,
            'mrn' => $mrn,
            'date_of_birth' => $validated['date_of_birth'] ?? null,
            'gender' => $validated['gender'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'email' => $validated['email'] ?? null,
            'address' => $validated['address'] ?? null,
            'user_id' => $validated['user_id'] ?? null,
        ]);

        return response()->json([
            'message' => 'Patient created successfully.',
            'data' => $patient,
        ], 201);
    }

    /**
     * Show a patient.
     */
    public function show(int $id): JsonResponse
    {
        $patient = Patient::query()->with(['visits.department', 'visits.activeQueueTicket'])->findOrFail($id);

        return response()->json([
            'data' => $patient,
        ]);
    }

    /**
     * Update a patient.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $patient = Patient::query()->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'nik' => ['nullable', 'string', 'max:50', 'unique:patients,nik,' . $id],
            'mrn' => ['nullable', 'string', 'max:50', 'unique:patients,mrn,' . $id],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'max:10'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string'],
        ]);

        $patient->update($validated);

        return response()->json([
            'message' => 'Patient updated successfully.',
            'data' => $patient,
        ]);
    }

    /**
     * Get visits for a patient.
     */
    public function visits(int $id): JsonResponse
    {
        $patient = Patient::query()->findOrFail($id);

        return response()->json([
            'data' => $patient->visits()->with(['department', 'workflow', 'activeQueueTicket'])->latest('id')->get(),
        ]);
    }
}
