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

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'source_visit_id',
        'target_department_id',
        'target_visit_id',
        'referred_by_user_id',
        'status',
        'priority',
        'reason',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'status' => ReferralStatus::class,
        'priority' => Priority::class,
    ];

    /**
     * Get the source visit that initiated the referral.
     */
    public function sourceVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'source_visit_id');
    }

    /**
     * Get the target department for this referral.
     */
    public function targetDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'target_department_id');
    }

    /**
     * Get the target visit created as a result of the referral, if any.
     */
    public function targetVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'target_visit_id');
    }
}
