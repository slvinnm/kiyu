<?php

namespace App\Models;

use App\Enums\IntakeChannel;
use App\Enums\Priority;
use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Visit extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'patient_id',
        'department_id',
        'workflow_id',
        'visit_number',
        'priority',
        'intake_channel',
        'status',
        'online_active_key',
        'registered_by',
        'checked_in_at',
        'registered_at',
        'completed_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'priority' => Priority::class,
        'intake_channel' => IntakeChannel::class,
        'status' => VisitStatus::class,
        'checked_in_at' => 'datetime',
        'registered_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Get the patient who owns this visit.
     */
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    /**
     * Get the department visited.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the workflow for this visit.
     */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the queue acquisition for this visit.
     */
    public function queueAcquisition(): HasOne
    {
        return $this->hasOne(QueueAcquisition::class)->latest('id');
    }

    /**
     * Get all queue acquisitions for this visit.
     */
    public function queueAcquisitions(): HasMany
    {
        return $this->hasMany(QueueAcquisition::class);
    }

    /**
     * Get the queue tickets generated for this visit.
     */
    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    /**
     * Get the currently active queue ticket, if any.
     */
    public function activeQueueTicket(): HasOne
    {
        return $this->hasOne(QueueTicket::class)
            ->whereIn('status', ['CREATED', 'CALLED', 'IN_PROGRESS', 'ON_HOLD'])
            ->latest('id');
    }

    /**
     * Get referrals where this visit is the source.
     */
    public function outgoingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'source_visit_id');
    }

    /**
     * Get referrals where this visit is the target.
     */
    public function incomingReferrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'target_visit_id');
    }
}
