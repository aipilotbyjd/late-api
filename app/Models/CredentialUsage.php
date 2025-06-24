<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CredentialUsage extends Model
{
    use HasFactory;

    protected $fillable = [
        'credential_id',
        'used_by_type',
        'used_by_id',
    ];

    public function credential(): BelongsTo
    {
        return $this->belongsTo(Credential::class);
    }

    public function usedBy(): MorphTo
    {
        return $this->morphTo('used_by', 'used_by_type', 'used_by_id');
    }
}
