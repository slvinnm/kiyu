<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceptionCallNextRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [
            UserRole::ADMIN,
            UserRole::RECEPTIONIST,
        ], true);
    }

    public function rules(): array
    {
        return [
            'department_code' => [
                'required',
                'string',
                'max:50',
                Rule::exists('departments', 'code')->where(
                    fn ($query) => $query->where('is_active', true),
                ),
            ],
        ];
    }
}
