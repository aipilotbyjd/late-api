<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\UsesUuidV4;

class WorkflowVersion extends Model
{
    use SoftDeletes, UsesUuidV4;
    
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'workflow_id',
        'version',
        'name',
        'description',
        'workflow_json',
        'is_active',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'workflow_json' => 'array',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the workflow that owns the version.
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }

    /**
     * Scope a query to only include active versions.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Activate this version and deactivate others.
     */
    public function activate(): void
    {
        // Deactivate all other versions
        $this->workflow->versions()
            ->where('id', '!=', $this->id)
            ->update(['is_active' => false]);

        // Activate this version
        $this->update(['is_active' => true]);

        // Update the workflow with this version's data
        $this->workflow->update([
            'workflow_json' => $this->workflow_json,
            'version' => $this->version,
        ]);
    }
}
