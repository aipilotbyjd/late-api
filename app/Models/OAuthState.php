<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OAuthState extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'provider',
        'state_token',
        'redirect_uri',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public static function createForUser(User $user, string $provider, string $redirectUri, int $ttl = 600): self
    {
        return static::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'state_token' => Str::random(40),
            'redirect_uri' => $redirectUri,
            'expires_at' => now()->addSeconds($ttl),
        ]);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isValid(): bool
    {
        return !$this->isExpired();
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
