<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\VisitStatus;
use App\Models\Patient;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOnlineVisit
{
    public function __construct(
        private CreateVisit $createVisit,
        private QueueAcquisitionService $queueAcquisitionService,
    ) {}

    public function handle(Patient $patient, string $departmentCode): Visit
    {
        return DB::transaction(function () use ($patient, $departmentCode) {
            $patient = Patient::query()
                ->whereKey($patient->id)
                ->lockForUpdate()
                ->firstOrFail();

            $hasActiveVisit = Visit::query()
                ->where('patient_id', $patient->id)
                ->whereHas('department', fn($query) => $query->where('code', $departmentCode))
                ->whereIn('status', [
                    VisitStatus::AWAITING_CHECKIN->value,
                    VisitStatus::CHECKED_IN->value,
                    VisitStatus::WAITING->value,
                    VisitStatus::IN_PROGRESS->value,
                ])
                ->exists();

            if ($hasActiveVisit) {
                throw ValidationException::withMessages([
                    'department_code' => 'The patient already has an active visit for this department.',
                ]);
            }

            $visit = $this->createVisit->handle(
                patientId: $patient->id,
                departmentCode: $departmentCode,
                intakeChannel: IntakeChannel::ONLINE,
                onlineActiveKey: $patient->id . '-' . $departmentCode,
            );

            $this->queueAcquisitionService->createForVisit(
                visit: $visit,
                channel: IntakeChannel::ONLINE,
            );

            return $visit;
        });
    }
}
