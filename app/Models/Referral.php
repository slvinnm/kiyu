<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\ReferralStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    protected $fillable = [
        'source_visit_id',
        'target_department_id',
        'target_visit_id',
        'referred_by_user_id',
        'status',
        'reason',
        'priority',
    ];

    protected $casts = [
        'status' => ReferralStatus::class,
        'priority' => Priority::class,
    ];

    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'source_visit_id');
    }

    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    public function targetVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'target_visit_id');
    }

    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_by_user_id');
    }
}
