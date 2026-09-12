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

    protected $fillable = [
        'patient_id',
        'department_id',
        'workflow_version_id',
        'visit_number',
        'priority',
        'intake_channel',
        'status',
        'registered_by',
        'checked_in_at',
        'completed_at',
    ];

    protected $casts = [
        'priority' => Priority::class,
        'intake_channel' => IntakeChannel::class,
        'status' => VisitStatus::class,
        'checked_in_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function registrar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by');
    }

    public function visitWorkflow(): HasOne
    {
        return $this->hasOne(VisitWorkflow::class);
    }

    public function queueAcquisition(): HasOne
    {
        return $this->hasOne(QueueAcquisition::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
