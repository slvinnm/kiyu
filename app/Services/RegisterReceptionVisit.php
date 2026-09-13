<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Models\Patient;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RegisterReceptionVisit
{
    public function __construct(private CreateVisit $createVisit) {}

    public function handle(array $data, User $registeredBy): Visit
    {
        return DB::transaction(function () use ($data, $registeredBy): Visit {
            $patient = isset($data['patient_id'])
                ? Patient::query()->findOrFail($data['patient_id'])
                : Patient::create(Arr::only($data, [
                    'name',
                    'email',
                    'national_id',
                    'date_of_birth',
                    'gender',
                    'phone',
                    'address',
                ]));

            return $this->createVisit->handle(
                patientId: $patient->id,
                departmentCode: $data['department_code'],
                intakeChannel: IntakeChannel::WALK_IN,
                registeredByUserId: $registeredBy->id,
            );
        });
    }
}
