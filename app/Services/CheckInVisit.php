<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;

class CheckInVisit
{
    /**
     * Check in an online visit for physical presence.
     *
     * Lifecycle:
     * AWAITING_CHECKIN -> CHECKED_IN -> WAITING
     *
     * The registration queue is created during registration and is reused.
     * Check-in never creates a second registration ticket.
     */
    public function handle(int $visitId, ?int $userId = null): Visit
    {
        return DB::transaction(function () use ($visitId, $userId) {
            $visit = Visit::query()
                ->whereKey($visitId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($visit->status !== VisitStatus::AWAITING_CHECKIN) {
                throw new \LogicException(
                    'Visit must be in AWAITING_CHECKIN state to check in. Current: ' .
                        ($visit->status instanceof VisitStatus ? $visit->status->value : $visit->status)
                );
            }

            $checkedInAt = now();

            // Persist the explicit lifecycle transition before entering the queue.
            $visit->update([
                'status' => VisitStatus::WAITING->value,
                'checked_in_at' => $checkedInAt,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'VISIT_CHECKED_IN',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'new_values' => [
                    'status' => VisitStatus::WAITING->value,
                    'checked_in_at' => $checkedInAt->toDateTimeString(),
                ],
            ]);

            return $visit->fresh();
        });
    }
}
