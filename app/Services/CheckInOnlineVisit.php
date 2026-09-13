<?php

namespace App\Services;

use App\Enums\IntakeChannel;
use App\Enums\VisitStatus;
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

            if ($visit->status === VisitStatus::CHECKED_IN) {
                return $visit->fresh([
                    'department',
                    'queueTickets.station',
                    'queueTickets.visitWorkflowStep.workflowStep',
                ]);
            }

            if ($visit->status !== VisitStatus::AWAITING_CHECKIN) {
                throw ValidationException::withMessages([
                    'visit' => 'This visit is not waiting for check-in.',
                ]);
            }

            $visit->update([
                'status' => VisitStatus::CHECKED_IN,
                'checked_in_at' => now(),
            ]);

            return $visit->fresh([
                'department',
                'queueTickets.station',
                'queueTickets.visitWorkflowStep.workflowStep',
            ]);
        });
    }
}
