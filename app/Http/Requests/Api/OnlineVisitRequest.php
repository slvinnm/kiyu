<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OnlineVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::PATIENT;
    }

    public function rules(): array
    {
        return [
            'department_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('departments', 'code')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ];
    }
}
