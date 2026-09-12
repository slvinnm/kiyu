<?php

namespace App\Http\Requests\Api;

use App\Enums\Priority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateReferralRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_department_id' => [
                'required',
                'integer',
                Rule::exists('departments', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
            'reason' => ['nullable', 'string', 'max:2000'],
            'priority' => [
                'nullable',
                'integer',
                Rule::in(array_map(
                    static fn (Priority $priority): int => $priority->value,
                    Priority::cases(),
                )),
            ],
        ];
    }
}
