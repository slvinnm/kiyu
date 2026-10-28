<?php

namespace App\Models;

use App\Enums\QueueEventType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'queue_ticket_id',
        'event_type',
        'from_status',
        'to_status',
        'user_id',
        'payload',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'event_type' => QueueEventType::class,
        'payload' => 'array',
    ];

    /**
     * Get the queue ticket this event belongs to.
     */
    public function queueTicket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class);
    }
}
