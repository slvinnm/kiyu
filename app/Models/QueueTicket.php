<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueTicket extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'visit_workflow_step_id',
        'station_id',
        'queue_number',
        'priority',
        'internal_sequence',
        'status',
        'called_at',
        'started_at',
        'completed_at',
        'assigned_to',
        'transfer_to_station_id',
        'transfer_target_station_id',
        'notes',
    ];

    protected $casts = [
        'priority' => Priority::class,
        'status' => QueueStatus::class,
        'called_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function visitWorkflowStep(): BelongsTo
    {
        return $this->belongsTo(VisitWorkflowStep::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class);
    }
}
