<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckInOnlineVisit
{
    public function handle(Visit $visit): Visit
    {
        return DB::transaction(function () use ($visit) {
            $visit = Visit::query()
                ->whereKey($visit->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($visit->intake_channel !== IntakeChannel::ONLINE) {
                throw ValidationException::withMessages([
                    'visit' => 'Only online visits can be checked in through this endpoint.',
                ]);
            }

            if ($visit->status === VisitStatus::WAITING) {
                return $visit->fresh([
                    'department',
                    'queueTickets.station',
                    'queueTickets.visitWorkflowStep.workflowStep',
                ]);
            }

            if (! in_array($visit->status, [VisitStatus::AWAITING_CHECKIN, VisitStatus::CHECKED_IN], true)) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit is not waiting for check-in.',
                ]);
            }

            $checkedInAt = now();

            $visit->update([
                'status' => VisitStatus::WAITING,
                'checked_in_at' => $checkedInAt,
            ]);

            AuditLog::create([
                'action' => 'VISIT_CHECKED_IN',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'new_values' => [
                    'status' => VisitStatus::WAITING->value,
                    'checked_in_at' => $checkedInAt->toDateTimeString(),
                ],
            ]);

            AuditLog::create([
                'action' => 'VISIT_WAITING_AFTER_CHECKIN',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'new_values' => [
                    'status' => VisitStatus::WAITING->value,
                ],
            ]);

            return $visit->fresh([
                'department',
                'queueTickets.station',
                'queueTickets.visitWorkflowStep.workflowStep',
            ]);
        });
    }
}
