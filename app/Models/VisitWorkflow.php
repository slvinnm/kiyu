<?php

namespace App\Models;

use App\Enums\VisitWorkflowStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VisitWorkflow extends Model
{
    use HasFactory;

    protected $fillable = [
        'visit_id',
        'workflow_version_id',
        'status',
    ];

    protected $casts = [
        'status' => VisitWorkflowStatus::class,
    ];

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(VisitWorkflowStep::class);
    }
}
