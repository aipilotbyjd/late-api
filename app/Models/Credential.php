<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Credential extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'team_id',
        'provider',
        'type',
        'name',
        'data',
        'meta',
        'shared_with',
        'expires_at',
        'last_refreshed_at',
    ];

    protected $casts = [
        'data' => 'encrypted:array',
        'meta' => 'array',
        'shared_with' => 'array',
        'expires_at' => 'datetime',
        'last_refreshed_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::orderedUuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(CredentialUsage::class);
    }

    public function usedBy(string $type, string $id): void
    {
        $this->usages()->create([
            'used_by_type' => $type,
            'used_by_id' => $id,
        ]);
    }
}
