<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\UsesUuidV4;

class ConnectedAccount extends Model
{
    use UsesUuidV4;
    
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'name',
        'email',
        'access_token',
        'refresh_token',
        'expires_at',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the connected account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Update the tokens for the connected account.
     */
    public function updateTokens(string $accessToken, ?string $refreshToken = null, ?string $expiresAt = null): void
    {
        $data = [
            'access_token' => $accessToken,
        ];

        if ($refreshToken) {
            $data['refresh_token'] = $refreshToken;
        }

        if ($expiresAt) {
            $data['expires_at'] = $expiresAt;
        }

        $this->update($data);
    }

    /**
     * Get the access token for the provider.
     */
    public function getAccessToken(): ?string
    {
        return $this->access_token;
    }

    /**
     * Get the refresh token for the provider.
     */
    public function getRefreshToken(): ?string
    {
        return $this->refresh_token;
    }

    /**
     * Get the metadata for the connected account.
     */
    public function getMetadata(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->metadata ?? [];
        }

        return $this->metadata[$key] ?? $default;
    }
}
