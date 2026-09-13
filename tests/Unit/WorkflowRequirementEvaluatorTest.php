<?php

use App\Services\WorkflowRequirementEvaluator;

it('passes empty requirements', function (): void {
    $evaluator = new WorkflowRequirementEvaluator;

    expect($evaluator->passes(null, []))->toBeTrue()
        ->and($evaluator->passes([], []))->toBeTrue();
});

it('evaluates all and any groups', function (): void {
    $evaluator = new WorkflowRequirementEvaluator;
    $context = [
        'visit' => ['priority' => 2],
        'clinical' => ['lab_ordered' => true],
    ];

    expect($evaluator->passes([
        'all' => [
            ['path' => 'visit.priority', 'operator' => 'equals', 'value' => 2],
            ['path' => 'clinical.lab_ordered', 'operator' => 'exists', 'value' => true],
        ],
    ], $context))->toBeTrue();

    expect($evaluator->passes([
        'any' => [
            ['path' => 'visit.priority', 'operator' => 'equals', 'value' => 3],
            ['path' => 'clinical.lab_ordered', 'operator' => 'equals', 'value' => true],
        ],
    ], $context))->toBeTrue();
});

it('supports comparison and membership operators', function (): void {
    $evaluator = new WorkflowRequirementEvaluator;
    $context = ['score' => 8, 'status' => 'ready', 'tags' => 'lab-order'];

    expect($evaluator->passes(['path' => 'score', 'operator' => 'greater_than', 'value' => 7], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'score', 'operator' => 'greater_than_or_equal', 'value' => 8], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'score', 'operator' => 'less_than', 'value' => 9], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'score', 'operator' => 'less_than_or_equal', 'value' => 8], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'status', 'operator' => 'in', 'value' => ['ready', 'done']], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'status', 'operator' => 'not_in', 'value' => ['blocked']], $context))->toBeTrue();
    expect($evaluator->passes(['path' => 'tags', 'operator' => 'contains', 'value' => 'lab'], $context))->toBeTrue();
});

it('returns false for missing paths and unsupported operators', function (): void {
    $evaluator = new WorkflowRequirementEvaluator;

    expect($evaluator->passes(['path' => 'missing.value', 'operator' => 'exists', 'value' => true], []))->toBeFalse();
    expect($evaluator->passes(['path' => 'value', 'operator' => 'unknown', 'value' => 1], ['value' => 1]))->toBeFalse();
    expect($evaluator->passes(['path' => 123], []))->toBeFalse();
});
