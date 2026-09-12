<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\ReferralStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Referral;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    public function __construct(private CreateVisit $createVisit) {}

    public function create(
        int $sourceVisitId,
        int $targetDepartmentId,
        ?int $referredByUserId = null,
        ?string $reason = null,
        ?Priority $priority = null,
    ): Referral {
        return DB::transaction(function () use ($sourceVisitId, $targetDepartmentId, $referredByUserId, $reason, $priority) {
            $sourceVisit = Visit::query()
                ->whereKey($sourceVisitId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($sourceVisit->patient_id === null) {
                throw ValidationException::withMessages([
                    'visit' => 'A referral requires a registered patient.',
                ]);
            }

            $targetDepartment = Department::query()
                ->whereKey($targetDepartmentId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($sourceVisit->department_id === $targetDepartment->id) {
                throw ValidationException::withMessages([
                    'target_department_id' => 'Referral target must be a different department.',
                ]);
            }

            $targetVisit = $this->createVisit->handle(
                patientId: $sourceVisit->patient_id,
                departmentCode: $targetDepartment->code,
                intakeChannel: IntakeChannel::WALK_IN,
                priority: $priority?->value ?? $sourceVisit->priority->value,
            );

            $referral = Referral::create([
                'source_visit_id' => $sourceVisit->id,
                'target_department_id' => $targetDepartment->id,
                'target_visit_id' => $targetVisit->id,
                'referred_by_user_id' => $referredByUserId,
                'status' => ReferralStatus::ACCEPTED->value,
                'reason' => $reason,
            ]);

            AuditLog::create([
                'user_id' => $referredByUserId,
                'action' => 'REFERRAL_CREATED',
                'auditable_type' => Referral::class,
                'auditable_id' => $referral->id,
                'new_values' => [
                    'source_visit_id' => $sourceVisit->id,
                    'target_visit_id' => $targetVisit->id,
                    'target_department_id' => $targetDepartment->id,
                ],
            ]);

            return $referral->fresh([
                'sourceVisit.department',
                'targetDepartment',
                'targetVisit.queueTickets',
            ]);
        });
    }
}
