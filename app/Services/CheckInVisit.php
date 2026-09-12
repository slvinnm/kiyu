<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Models\QueueTicket;
use Illuminate\Support\Facades\DB;

class CheckInVisit
{
    /**
     * Check in a visit for physical presence.
     *
     * Required lifecycle:
     *   AWAITING_CHECKIN → CHECKED_IN → WAITING
     *
     * For online visits:
     *   - Existing queue ticket must be reused
     *   - Physical check-in must NOT create another registration ticket
     *
     * For kiosk/walk-in visits:
     *   - Initial queue behavior follows the configured workflow
     *
     * @param int $visitId
     * @return Visit
     */
    public function handle(int $visitId): Visit
    {
        return DB::transaction(function () use ($visitId) {
            // 1. Find visit with relationships
            $visit = Visit::with(['patient', 'department', 'workflowVersion'])->findOrFail($visitId);

            // 2. Validate current state
            if ($visit->status !== VisitStatus::AWAITING_CHECKIN->value) {
                throw new \LogicException("Visit must be in AWAITING_CHECKIN state to check in. Current: {$visit->status}");
            }

            // 3. Transition to CHECKED_IN
            $visit->update([
                'status' => VisitStatus::CHECKED_IN->value,
                'checked_in_at' => now(),
            ]);

            // 4. For online visits, ensure we don't create duplicate registration ticket
            // The initial queue ticket should already exist from online registration
            // No action needed here - the ticket exists and will be progressed normally

            // 5. Return refreshed visit
            return $visit->fresh();
        });
    }
}