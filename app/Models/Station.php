<?php

namespace App\Models;

use App\Enums\StationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Station extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'name',
        'code',
        'type',
        'queue_prefix',
        'is_active',
    ];

    protected $casts = [
        'type' => StationType::class,
        'is_active' => 'boolean',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workflowSteps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class);
    }

    public function queueTickets(): HasMany
    {
        return $this->hasMany(QueueTicket::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function counters(): HasMany
    {
        return $this->hasMany(QueueCounter::class);
    }
}
