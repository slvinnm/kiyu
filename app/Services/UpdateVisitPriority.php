<?php

namespace App\Services;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateVisitPriority
{
    public function handle(Visit $visit, Priority $priority, User $user): Visit
    {
        return DB::transaction(function () use ($visit, $priority, $user) {
            $visit = Visit::query()->whereKey($visit->id)->lockForUpdate()->firstOrFail();

            if ($user->role->value !== 'admin' && $user->department_id !== $visit->department_id) {
                throw ValidationException::withMessages(['visit' => 'You are not authorized to change priority for this visit.']);
            }

            if (in_array($visit->status->value, ['COMPLETED', 'CANCELLED'], true)) {
                throw ValidationException::withMessages(['priority' => 'Priority cannot be changed for a completed or cancelled visit.']);
            }

            $oldPriority = $visit->priority;

            if ($oldPriority === $priority) {
                return $visit->fresh(['queueTickets']);
            }

            $visit->update(['priority' => $priority->value]);

            $visit->queueTickets()
                ->whereIn('status', [
                    QueueStatus::CREATED->value,
                    QueueStatus::CALLED->value,
                    QueueStatus::ON_HOLD->value,
                ])
                ->update(['priority' => $priority->value]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'VISIT_PRIORITY_CHANGED',
                'auditable_type' => Visit::class,
                'auditable_id' => $visit->id,
                'old_values' => ['priority' => $oldPriority->value],
                'new_values' => ['priority' => $priority->value],
            ]);

            return $visit->fresh(['queueTickets']);
        });
    }
}
