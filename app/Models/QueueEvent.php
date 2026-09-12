<?php

namespace App\Models;

use App\Enums\QueueEventType;
use App\Enums\QueueStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'queue_ticket_id',
        'event_type',
        'from_status',
        'to_status',
        'user_id',
        'payload',
    ];

    protected $casts = [
        'event_type' => QueueEventType::class,
        'from_status' => QueueStatus::class,
        'to_status' => QueueStatus::class,
        'payload' => 'array',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueueTicket::class, 'queue_ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
