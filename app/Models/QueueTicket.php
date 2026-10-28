<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\QueueTicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueueTicket extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'workflow_step_id',
        'station_id',
        'queue_number',
        'priority',
        'internal_sequence',
        'status',
        'assigned_to',
        'transferred_from_ticket_id',
        'transferred_to_station_id',
        'notes',
        'called_at',
        'started_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => Priority::class,
        'internal_sequence' => 'integer',
        'status' => QueueTicketStatus::class,
        'called_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the visit that owns this ticket.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the workflow step for this ticket.
     */
    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    /**
     * Get the station serving this ticket.
     */
    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    /**
     * Get the prior ticket if this ticket was transferred from another ticket.
     */
    public function transferredFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transferred_from_ticket_id');
    }

    /**
     * Get the station this ticket was transferred to, if any.
     */
    public function transferredToStation(): BelongsTo
    {
        return $this->belongsTo(Station::class, 'transferred_to_station_id');
    }

    /**
     * Get the events recorded for this ticket.
     */
    public function events(): HasMany
    {
        return $this->hasMany(QueueEvent::class)->orderBy('id');
    }
}
