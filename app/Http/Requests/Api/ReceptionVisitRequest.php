<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReceptionVisitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array($this->user()?->role, [UserRole::ADMIN, UserRole::RECEPTIONIST], true);
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
            'patient_id' => ['nullable', 'integer', 'exists:patients,id', 'required_without:name'],
            'name' => ['nullable', 'string', 'max:255', 'required_without:patient_id'],
            'email' => ['nullable', 'email', 'max:255'],
            'national_id' => ['nullable', 'string', 'max:20', Rule::unique('patients', 'national_id')],
            'date_of_birth' => ['nullable', 'date'],
            'gender' => ['nullable', 'string', 'in:male,female'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
        ];
    }
}
