<?php

namespace App\Models;

use App\Enums\IntakeChannel;
use App\Enums\QueueAcquisitionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueueAcquisition extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'visit_id',
        'channel',
        'status',
        'acquired_at',
    ];

    protected $casts = [
        'channel' => IntakeChannel::class,
        'status' => QueueAcquisitionStatus::class,
        'acquired_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }
}
