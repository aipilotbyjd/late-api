<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\UsesUuidV4;

class WorkflowExecutionLog extends Model
{
    use UsesUuidV4;
    
    protected $keyType = 'string';
    public $incrementing = false;

    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_DEBUG = 'debug';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workflow_execution_id',
        'node_id',
        'node_name',
        'node_type',
        'level',
        'message',
        'data',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the execution that owns the log.
     */
    public function execution()
    {
        return $this->belongsTo(WorkflowExecution::class, 'workflow_execution_id');
    }

    /**
     * Create a new log entry.
     */
    public static function createLog(
        int $executionId,
        string $level,
        string $message,
        ?string $nodeId = null,
        ?string $nodeName = null,
        ?string $nodeType = null,
        ?array $data = null
    ): self {
        return static::create([
            'workflow_execution_id' => $executionId,
            'node_id' => $nodeId,
            'node_name' => $nodeName,
            'node_type' => $nodeType,
            'level' => $level,
            'message' => $message,
            'data' => $data,
        ]);
    }
}
