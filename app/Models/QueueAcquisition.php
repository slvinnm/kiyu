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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'visit_id',
        'department_id',
        'channel',
        'status',
        'idempotency_key',
        'registered_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'channel' => IntakeChannel::class,
        'status' => QueueAcquisitionStatus::class,
    ];

    /**
     * Get the visit associated with this acquisition.
     */
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    /**
     * Get the department for this acquisition.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
