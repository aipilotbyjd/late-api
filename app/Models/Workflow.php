<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use SoftDeletes;

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
    ];

    /**
     * Get the project that owns the workflow.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
