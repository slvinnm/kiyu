<?php

namespace App\Http\Requests\Api;

use App\Enums\UserRole;
use App\Models\QueueAcquisition;
use Illuminate\Foundation\Http\FormRequest;

class ReceptionRegisterQueueAcquisitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $acquisition = $this->route('queueAcquisition');

        if (! $user || ! $acquisition instanceof QueueAcquisition) {
            return false;
        }

        if ($user->role === UserRole::ADMIN) {
            return true;
        }

        return $user->role === UserRole::RECEPTIONIST;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
        ];
    }
}
