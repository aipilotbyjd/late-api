<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowExecution extends Model
{
    use SoftDeletes;

    const STATUS_RUNNING = 'running';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_TIMED_OUT = 'timed_out';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workflow_id',
        'status',
        'started_at',
        'finished_at',
        'execution_time',
        'error',
        'input',
        'output',
        'trigger_type',
        'trigger_data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'input' => 'array',
        'output' => 'array',
        'trigger_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the workflow that owns the execution.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Get the execution logs.
     */
    public function logs()
    {
        return $this->hasMany(WorkflowExecutionLog::class);
    }

    /**
     * Mark the execution as completed.
     */
    public function markAsCompleted(array $output = []): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'finished_at' => now(),
            'execution_time' => $this->started_at->diffInMilliseconds(now()),
            'output' => $output,
        ]);
    }

    /**
     * Mark the execution as failed.
     */
    public function markAsFailed(string $error, array $context = []): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'finished_at' => now(),
            'execution_time' => $this->started_at->diffInMilliseconds(now()),
            'error' => $error,
            'output' => $context,
        ]);
    }
}
