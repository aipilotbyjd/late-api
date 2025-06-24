<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\Credentials\CredentialStorageService;

class OAuthController extends Controller
{
    public function init($provider)
    {
        $state = Str::uuid();
        session(['oauth_state' => $state]);

        $config = config("oauth_providers.$provider");
        $url = $config['auth_url'] . '?' . http_build_query([
            'client_id' => $config['client_id'],
            'redirect_uri' => $config['redirect_uri'],
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state
        ]);

        return redirect()->away($url);
    }

    public function callback(Request $request, $provider)
    {
        if ($request->state !== session('oauth_state')) {
            abort(403, 'Invalid OAuth state');
        }

        $config = config("oauth_providers.$provider");

        $response = Http::asForm()->post($config['token_url'], [
            'grant_type' => 'authorization_code',
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $config['redirect_uri'],
            'code' => $request->code
        ]);

        $tokenData = $response->json();
        $meta = ['email' => $this->getEmailFromToken($tokenData, $provider)];

        app(CredentialStorageService::class)->store(
            $provider,
            'oauth2',
            ucfirst($provider) . ' Account',
            $tokenData,
            $meta
        );

        return redirect('/connected-accounts');
    }

    protected function getEmailFromToken(array $token, string $provider)
    {
        return $provider . '@example.com';
    }
}
