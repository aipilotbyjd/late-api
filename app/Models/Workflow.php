<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use SoftDeletes;

    const STATUS_ACTIVE = 'active';
    const STATUS_PAUSED = 'paused';
    const STATUS_DRAFT = 'draft';
    const STATUS_ERROR = 'error';

    const TRIGGER_WEBHOOK = 'webhook';
    const TRIGGER_POLLING = 'polling';
    const TRIGGER_SCHEDULE = 'schedule';
    const TRIGGER_MANUAL = 'manual';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'project_id',
        'name',
        'description',
        'status',
        'workflow_json',
        'trigger_type',
        'webhook_token',
        'is_public',
        'cron_expression',
        'last_run_at',
        'version',
        'settings',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'workflow_json' => 'array',
        'is_public' => 'boolean',
        'last_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->webhook_token)) {
                $model->webhook_token = Str::random(40);
            }
            if (empty($model->version)) {
                $model->version = '1.0.0';
            }
        });
    }

    /**
     * Get the project that owns the workflow.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the workflow's executions.
     */
    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
    }

    /**
     * Get the workflow's versions.
     */
    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    /**
     * Check if the workflow is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Get the webhook URL for this workflow.
     *
     * @return string
     */
    public function getWebhookUrl(): string
    {
        return route('api.workflows.webhook', [
            'workflow' => $this->id,
            'token' => $this->webhook_token,
        ]);
    }
}
