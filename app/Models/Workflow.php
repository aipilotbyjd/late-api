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
    const TRIGGER_SLACK = 'slack';
    const TRIGGER_GMAIL = 'gmail';

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
        'trigger_type',
        'webhook_token',
        'is_public',
        'cron_expression',
        'last_run_at',
        'active_version_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_public' => 'boolean',
        'last_run_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
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
        });
    }

    /**
     * Get the project that the workflow belongs to.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get all versions of the workflow.
     */
    public function versions()
    {
        return $this->hasMany(WorkflowVersion::class)->latest('id');
    }

    /**
     * Get the latest version of the workflow.
     */
    public function latestVersion()
    {
        return $this->hasOne(WorkflowVersion::class)->latest('id');
    }

    /**
     * Get the active version of the workflow.
     */
    public function activeVersion()
    {
        return $this->belongsTo(WorkflowVersion::class, 'active_version_id');
    }

    /**
     * Get all executions for the workflow.
     */
    public function executions()
    {
        return $this->hasMany(WorkflowExecution::class);
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
