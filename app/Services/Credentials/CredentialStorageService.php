<?php

namespace App\Services\Credentials;

use App\Models\Credential;
use Illuminate\Support\Facades\Crypt;

class CredentialStorageService
{
    public function store($user, $provider, $type, $tokenData, $meta)
    {
        return Credential::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'type' => $type,
            'name' => ucfirst($provider) . " Account",
            'data' => Crypt::encryptString(json_encode($tokenData)),
            'meta' => $meta,
            'expires_at' => now()->addSeconds($tokenData['expires_in'] ?? 3600),
            'last_refreshed_at' => now()
        ]);
    }

    public function getDecrypted(Credential $credential)
    {
        return json_decode(Crypt::decryptString($credential->data), true);
    }
}
