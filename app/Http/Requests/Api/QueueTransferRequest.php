<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QueueTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_station_id' => [
                'required',
                'integer',
                Rule::exists('stations', 'id')->where(
                    fn ($query) => $query->where('is_active', true)
                ),
            ],
        ];
    }
}
