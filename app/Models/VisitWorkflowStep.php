<?php

namespace App\Models;

use App\Enums\VisitWorkflowStepStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitWorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_workflow_id',
        'workflow_step_id',
        'status',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'status' => VisitWorkflowStepStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function visitWorkflow(): BelongsTo
    {
        return $this->belongsTo(VisitWorkflow::class);
    }

    public function workflowStep(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }
}
