<?php

namespace App\Http\Resources;

use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->role instanceof UserRole
            ? $this->role->value
            : $this->role;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'profile' => $this->when(
                $this->relationLoaded('patient') && $this->patient,
                fn() => new PatientResource($this->patient),
            ),
        ];
    }
}
