<?php

namespace App\Services;

use Illuminate\Support\Arr;

class WorkflowRequirementEvaluator
{
    /**
     * Evaluate a declarative rule tree against runtime workflow context.
     *
     * Supported forms:
     *
     * [
     *     'all' => [
     *         ['path' => 'clinical.lab_ordered', 'operator' => 'equals', 'value' => true],
     *     ],
     * ]
     *
     * [
     *     'any' => [
     *         ['path' => 'visit.priority', 'operator' => 'in', 'value' => [50, 100]],
     *     ],
     * ]
     *
     * A single condition may also be supplied without all/any.
     */
    public function passes(?array $requirements, array $context): bool
    {
        if ($requirements === null || $requirements === []) {
            return true;
        }

        if (array_key_exists('all', $requirements)) {
            return collect($requirements['all'])
                ->every(fn($condition) => $this->evaluateNode($condition, $context));
        }

        if (array_key_exists('any', $requirements)) {
            return collect($requirements['any'])
                ->contains(fn($condition) => $this->evaluateNode($condition, $context));
        }

        return $this->evaluateNode($requirements, $context);
    }

    private function evaluateNode(array $condition, array $context): bool
    {
        if (array_key_exists('all', $condition)) {
            return collect($condition['all'])
                ->every(fn($node) => $this->evaluateNode($node, $context));
        }

        if (array_key_exists('any', $condition)) {
            return collect($condition['any'])
                ->contains(fn($node) => $this->evaluateNode($node, $context));
        }

        $path = $condition['path'] ?? null;
        $operator = $condition['operator'] ?? 'equals';

        if (! is_string($path) || ! is_string($operator)) {
            return false;
        }

        $exists = Arr::has($context, $path);
        $actual = Arr::get($context, $path);
        $expected = $condition['value'] ?? null;

        return match ($operator) {
            'exists' => $exists === (bool) ($condition['value'] ?? true),
            'equals', '==' => $actual === $expected,
            'not_equals', '!=' => $actual !== $expected,
            'in' => is_array($expected) && in_array($actual, $expected, true),
            'not_in' => is_array($expected) && ! in_array($actual, $expected, true),
            'contains' => is_string($actual) && is_string($expected) && str_contains($actual, $expected),
            'greater_than', '>' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'greater_than_or_equal', '>=' => is_numeric($actual) && is_numeric($expected) && $actual >= $expected,
            'less_than', '<' => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'less_than_or_equal', '<=' => is_numeric($actual) && is_numeric($expected) && $actual <= $expected,
            default => false,
        };
    }
}
