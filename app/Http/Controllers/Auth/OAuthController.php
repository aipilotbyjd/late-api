<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OAuthController extends Controller
{
    /**
     * The provider name.
     *
     * @var string|null
     */
    protected $provider = null;

    /**
     * The OAuth scopes.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * Whether to use the base controller's redirect and callback methods.
     *
     * @var bool
     */
    protected $useBaseImplementation = true;
    /**
     * Redirect to the provider's OAuth page.
     *
     * @param  string|null  $provider
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    public function redirect($provider = null)
    {
        // If no provider is passed, use the one from the class property
        $provider = $provider ?? $this->provider;

        if (!$provider) {
            return response()->json(['error' => 'No provider specified'], 400);
        }

        // If a child class has its own implementation, use that
        if (!$this->useBaseImplementation) {
            return $this->handleRedirect();
        }

        $providerKey = config("services.{$provider}");

        if (!$providerKey) {
            return response()->json(['error' => 'Invalid provider'], 400);
        }

        $state = Str::random(40);
        session(['oauth_state' => $state]);
        session(['oauth_provider' => $provider]);

        $query = http_build_query([
            'client_id' => config("services.{$provider}.client_id"),
            'redirect_uri' => route('oauth.' . $provider . '.callback'),
            'response_type' => 'code',
            'scope' => $this->getScopes($provider),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect(
            config("services.{$provider}.auth_uri") . '?' . $query
        );
    }

    /**
     * Handle the OAuth redirect.
     * Can be overridden by child classes if needed.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function handleRedirect()
    {
        // Default implementation does nothing
        return response()->json(['error' => 'Not implemented'], 501);
    }

    /**
     * Handle the OAuth callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string|null  $provider
     * @return \Illuminate\Http\JsonResponse
     */
    public function callback(Request $request, $provider = null)
    {
        // If no provider is passed, use the one from the class property
        $provider = $provider ?? $this->provider;

        if (!$provider) {
            return response()->json(['error' => 'No provider specified'], 400);
        }

        // If a child class has its own implementation, use that
        if (!$this->useBaseImplementation) {
            return $this->handleCallback($request);
        }

        if ($request->state !== session('oauth_state') || session('oauth_provider') !== $provider) {
            return response()->json(['error' => 'Invalid state'], 400);
        }

        try {
            $tokenResponse = Http::asForm()->post(config("services.{$provider}.token_uri"), [
                'grant_type' => 'authorization_code',
                'client_id' => config("services.{$provider}.client_id"),
                'client_secret' => config("services.{$provider}.client_secret"),
                'redirect_uri' => route('oauth.' . $provider . '.callback'),
                'code' => $request->code,
            ]);

            $tokenData = $tokenResponse->json();

            if (!$tokenResponse->ok()) {
                Log::error('OAuth token error', [
                    'provider' => $provider,
                    'response' => $tokenData,
                    'status' => $tokenResponse->status(),
                ]);
                throw new \Exception($tokenData['error_description'] ?? 'Failed to get access token');
            }

            $userInfo = $this->getUserInfo($tokenData['access_token']);

            $user = $request->user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            $account = $user->connectedAccounts()->updateOrCreate(
                ['provider' => $provider, 'provider_id' => $userInfo['id']],
                [
                    'name' => $userInfo['name'] ?? 'Unknown',
                    'email' => $userInfo['email'] ?? null,
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'] ?? null,
                    'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                    'metadata' => $userInfo,
                ]
            );

            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Successfully connected to ' . ucfirst($provider),
            ]);
        } catch (\Exception $e) {
            Log::error('OAuth Error: ' . $e->getMessage(), [
                'provider' => $provider,
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'OAuth Error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Handle the OAuth callback.
     * Can be overridden by child classes if needed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleCallback(Request $request)
    {
        // Default implementation does nothing
        return response()->json(['error' => 'Not implemented'], 501);
    }

    /**
     * Get the scopes for the provider.
     *
     * @param  string|null  $provider
     * @return string
     */
    protected function getScopes($provider = null)
    {
        $provider = $provider ?? $this->provider;
        if (!$provider) {
            return '';
        }

        $scopes = config("services.{$provider}.scopes", implode(' ', $this->scopes));
        return is_array($scopes) ? implode(' ', $scopes) : $scopes;
    }

    /**
     * Get user info from the access token.
     * This method should be overridden by child classes.
     *
     * @param  string  $accessToken
     * @return array
     * @throws \Exception
     */
    protected function getUserInfo($accessToken)
    {
        // This method should be overridden by child classes
        return [];
    }

    /**
     * Get the authenticated user.
     * Can be overridden by child classes if needed.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function getAuthenticatedUser(Request $request)
    {
        return $request->user();
    }

    /**
     * Handle successful authentication.
     * Can be overridden by child classes if needed.
     *
     * @param  \App\Models\User  $user
     * @param  string  $provider
     * @param  array  $userInfo
     * @param  array  $tokenData
     * @return \App\Models\ConnectedAccount
     */
    protected function handleSuccessfulAuthentication($user, $provider, $userInfo, $tokenData)
    {
        $accountData = [
            'name' => $userInfo['name'] ?? 'Unknown',
            'email' => $userInfo['email'] ?? null,
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? null,
            'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
            'metadata' => $userInfo,
        ];

        return $user->connectedAccounts()->updateOrCreate(
            ['provider' => $provider, 'provider_id' => $userInfo['id'] ?? null],
            $accountData
        );
    }
}
