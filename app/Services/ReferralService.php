<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Enums\ReferralStatus;
use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Patient;
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

            if (! in_array($sourceVisit->status, [
                VisitStatus::CHECKED_IN,
                VisitStatus::WAITING,
                VisitStatus::IN_PROGRESS,
            ], true)) {
                throw ValidationException::withMessages([
                    'visit' => 'Only active visits can be referred.',
                ]);
            }

            Patient::query()
                ->whereKey($sourceVisit->patient_id)
                ->lockForUpdate()
                ->firstOrFail();

            $targetDepartment = Department::query()
                ->whereKey($targetDepartmentId)
                ->where('is_active', true)
                ->firstOrFail();

            if ($sourceVisit->department_id === $targetDepartment->id) {
                throw ValidationException::withMessages([
                    'target_department_id' => 'Referral target must be a different department.',
                ]);
            }

            $targetVisit = Visit::query()
                ->where('patient_id', $sourceVisit->patient_id)
                ->where('department_id', $targetDepartment->id)
                ->whereIn('status', [
                    VisitStatus::AWAITING_CHECKIN->value,
                    VisitStatus::CHECKED_IN->value,
                    VisitStatus::WAITING->value,
                    VisitStatus::IN_PROGRESS->value,
                ])
                ->whereHas('queueTickets', function ($query): void {
                    $query->whereIn('status', [
                        QueueStatus::CREATED->value,
                        QueueStatus::CALLED->value,
                        QueueStatus::IN_PROGRESS->value,
                        QueueStatus::ON_HOLD->value,
                    ]);
                })
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $joinedExistingVisit = $targetVisit !== null;

            if (! $targetVisit) {
                $targetVisit = $this->createVisit->handle(
                    patientId: $sourceVisit->patient_id,
                    departmentCode: $targetDepartment->code,
                    intakeChannel: IntakeChannel::WALK_IN,
                    priority: $priority?->value ?? $sourceVisit->priority->value,
                );
            } elseif ($priority !== null && $priority->value > $targetVisit->priority->value) {
                $targetVisit->update([
                    'priority' => $priority->value,
                ]);

                $targetVisit->queueTickets()
                    ->whereIn('status', [
                        QueueStatus::CREATED->value,
                        QueueStatus::CALLED->value,
                        QueueStatus::IN_PROGRESS->value,
                        QueueStatus::ON_HOLD->value,
                    ])
                    ->update([
                        'priority' => $priority->value,
                    ]);
            }

            $duplicateReferral = Referral::query()
                ->where('source_visit_id', $sourceVisit->id)
                ->where('target_visit_id', $targetVisit->id)
                ->where('status', ReferralStatus::ACCEPTED->value)
                ->exists();

            if ($duplicateReferral) {
                throw ValidationException::withMessages([
                    'target_department_id' => 'An active referral already exists for this target visit.',
                ]);
            }

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
                    'joined_existing_visit' => $joinedExistingVisit,
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
