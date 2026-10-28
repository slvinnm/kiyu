<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    /**
     * List active departments with stations (for kiosk display).
     */
    public function active(): JsonResponse
    {
        return response()->json([
            'data' => Department::query()
                ->with('stations')
                ->where('is_active', true)
                ->get(),
        ]);
    }

    /**
     * List all departments.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Department::query()->with('stations')->get(),
        ]);
    }

    /**
     * Create a department.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50', 'unique:departments,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department = Department::query()->create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Department created successfully.',
            'data' => $department,
        ], 201);
    }

    /**
     * Show a department.
     */
    public function show(int $id): JsonResponse
    {
        $department = Department::query()->with(['stations', 'workflows.steps.station'])->findOrFail($id);

        return response()->json([
            'data' => $department,
        ]);
    }

    /**
     * Update a department.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $department = Department::query()->findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', 'unique:departments,code,' . $id],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $department->update($validated);

        return response()->json([
            'message' => 'Department updated successfully.',
            'data' => $department,
        ]);
    }

    /**
     * List stations for a department.
     */
    public function stations(int $id): JsonResponse
    {
        $department = Department::query()->findOrFail($id);

        return response()->json([
            'data' => $department->stations,
        ]);
    }

    /**
     * List workflows for a department.
     */
    public function workflows(int $id): JsonResponse
    {
        $department = Department::query()->findOrFail($id);

        return response()->json([
            'data' => $department->workflows()->with('steps.station')->get(),
        ]);
    }
}
