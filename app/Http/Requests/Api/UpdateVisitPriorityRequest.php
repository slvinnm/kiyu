<?php

namespace App\Http\Requests\Api;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitPriorityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'priority' => [
                'required',
                'integer',
                Rule::in(array_map(
                    static fn(Priority $priority): int => $priority->value,
                    Priority::cases(),
                )),
            ],
        ];
    }
}
