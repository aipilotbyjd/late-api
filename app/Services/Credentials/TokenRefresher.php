<?php

namespace App\Services\Credentials;

use App\Models\Credential;
use Illuminate\Support\Facades\Http;

class TokenRefresher
{
    public function refreshIfNeeded(Credential $credential)
    {
        if ($credential->type !== 'oauth2') return;

        if (now()->lt($credential->expires_at)) return; // Still valid

        $data = $credential->data;
        $provider = $credential->provider;
        $config = config("oauth_providers.$provider");

        $response = Http::asForm()->post($config['token_url'], [
            'grant_type' => 'refresh_token',
            'refresh_token' => $data['refresh_token'] ?? null,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
        ]);

        if (!$response->successful()) {
            throw new \Exception("Failed to refresh token for $provider");
        }

        $newToken = array_merge($data, $response->json());
        $credential->update([
            'data' => $newToken,
            'expires_at' => now()->addSeconds($newToken['expires_in'] ?? 3600),
            'last_refreshed_at' => now(),
        ]);
    }
}
