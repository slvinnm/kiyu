<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowStep extends Model
{
    use HasFactory;

    protected $fillable = [
        'workflow_version_id',
        'station_id',
        'name',
        'sequence',
        'requires_queue',
        'is_optional',
        'is_repeatable',
        'can_skip',
        'completion_requirements',
        'entry_conditions',
    ];

    protected $casts = [
        'requires_queue' => 'boolean',
        'is_optional' => 'boolean',
        'is_repeatable' => 'boolean',
        'can_skip' => 'boolean',
        'completion_requirements' => 'array',
        'entry_conditions' => 'array',
    ];

    public function workflowVersion(): BelongsTo
    {
        return $this->belongsTo(WorkflowVersion::class);
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function stations(): BelongsToMany
    {
        return $this->belongsToMany(Station::class, 'workflow_step_stations');
    }

    public function visitWorkflowSteps(): HasMany
    {
        return $this->hasMany(VisitWorkflowStep::class);
    }
}
