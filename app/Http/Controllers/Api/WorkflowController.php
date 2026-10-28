<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Workflow;
use App\Models\WorkflowStep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    /**
     * List workflows.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Workflow::query()->with(['department', 'steps.station']);

        if ($request->has('department_id')) {
            $query->where('department_id', $request->input('department_id'));
        }

        return response()->json([
            'data' => $query->get(),
        ]);
    }

    /**
     * Create a workflow.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'department_id' => ['required', 'integer', 'exists:departments,id'],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $workflow = Workflow::query()->create([
            'department_id' => $validated['department_id'],
            'name' => $validated['name'],
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Workflow created successfully.',
            'data' => $workflow->load('department'),
        ], 201);
    }

    /**
     * Show a workflow with its steps.
     */
    public function show(int $id): JsonResponse
    {
        $workflow = Workflow::query()->with(['department', 'steps.station'])->findOrFail($id);

        return response()->json([
            'data' => $workflow,
        ]);
    }

    /**
     * Add a step to a workflow.
     */
    public function addStep(Request $request, int $id): JsonResponse
    {
        $workflow = Workflow::query()->findOrFail($id);

        $validated = $request->validate([
            'station_id' => ['required', 'integer', 'exists:stations,id'],
            'name' => ['required', 'string', 'max:255'],
            'sequence' => ['required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'is_optional' => ['nullable', 'boolean'],
            'is_repeatable' => ['nullable', 'boolean'],
            'can_skip' => ['nullable', 'boolean'],
            'completion_requirements' => ['nullable', 'array'],
            'entry_conditions' => ['nullable', 'array'],
        ]);

        $step = $workflow->steps()->create([
            'station_id' => $validated['station_id'],
            'name' => $validated['name'],
            'sequence' => $validated['sequence'],
            'is_active' => $validated['is_active'] ?? true,
            'is_optional' => $validated['is_optional'] ?? false,
            'is_repeatable' => $validated['is_repeatable'] ?? false,
            'can_skip' => $validated['can_skip'] ?? false,
            'completion_requirements' => $validated['completion_requirements'] ?? null,
            'entry_conditions' => $validated['entry_conditions'] ?? null,
        ]);

        return response()->json([
            'message' => 'Workflow step added successfully.',
            'data' => $step->load('station'),
        ], 201);
    }

    /**
     * Update a workflow step.
     */
    public function updateStep(Request $request, int $id): JsonResponse
    {
        $step = WorkflowStep::query()->findOrFail($id);

        $validated = $request->validate([
            'station_id' => ['sometimes', 'required', 'integer', 'exists:stations,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'sequence' => ['sometimes', 'required', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
            'is_optional' => ['nullable', 'boolean'],
            'is_repeatable' => ['nullable', 'boolean'],
            'can_skip' => ['nullable', 'boolean'],
            'completion_requirements' => ['nullable', 'array'],
            'entry_conditions' => ['nullable', 'array'],
        ]);

        $step->update($validated);

        return response()->json([
            'message' => 'Workflow step updated successfully.',
            'data' => $step->load('station'),
        ]);
    }

    /**
     * Delete a workflow step.
     */
    public function deleteStep(int $id): JsonResponse
    {
        $step = WorkflowStep::query()->findOrFail($id);
        $step->delete();

        return response()->json([
            'message' => 'Workflow step deleted successfully.',
        ]);
    }
}
