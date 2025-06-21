<?php

namespace App\Http\Controllers\Auth;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GoogleOAuthController extends OAuthController
{
    /**
     * The provider name.
     *
     * @var string
     */
    protected $provider = 'google';

    /**
     * Whether to use the base controller's redirect and callback methods.
     *
     * @var bool
     */
    protected $useBaseImplementation = false;

    /**
     * The OAuth scopes.
     *
     * @var array
     */
    protected $scopes = [
        'https://www.googleapis.com/auth/gmail.send',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ];
    /**
     * Handle the OAuth redirect.
     *
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
     */
    protected function handleRedirect()
    {
        $state = Str::random(40);
        session(['oauth_state' => $state]);
        session(['oauth_provider' => $this->provider]);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => route('oauth.google.callback'),
            'response_type' => 'code',
            'scope' => implode(' ', $this->scopes),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return redirect('https://accounts.google.com/o/oauth2/auth?' . $query);
    }
    /**
     * Handle the OAuth callback.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    protected function handleCallback(Request $request)
    {
        try {
            // Verify state
            if ($request->state !== session('oauth_state')) {
                throw new \Exception('Invalid state parameter');
            }

            // Exchange authorization code for access token
            $tokenData = $this->getAccessToken($request->code);

            if (!isset($tokenData['access_token'])) {
                throw new \Exception('Failed to obtain access token');
            }

            // Get user info
            $userInfo = $this->getUserInfo($tokenData['access_token']);

            // Get authenticated user
            $user = $request->user();
            if (!$user) {
                throw new \Exception('User not authenticated');
            }

            // Prepare account data
            $accountData = [
                'name' => $userInfo['name'] ?? 'Google User',
                'email' => $userInfo['email'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                'metadata' => array_merge($userInfo, [
                    'verified_email' => $userInfo['verified_email'] ?? false,
                    'picture' => $userInfo['picture'] ?? null,
                    'locale' => $userInfo['locale'] ?? null,
                ]),
            ];

            // Create or update connected account
            $account = $user->connectedAccounts()->updateOrCreate(
                ['provider' => $this->provider, 'provider_id' => $userInfo['id']],
                $accountData
            );

            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Successfully connected to Google',
            ]);
        } catch (\Exception $e) {
            Log::error('Google OAuth Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Failed to authenticate with Google',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get access token from authorization code.
     *
     * @param  string  $code
     * @return array
     */
    protected function getAccessToken($code)
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => route('oauth.google.callback'),
        ]);

        $data = $response->json();

        if (!$response->ok() || isset($data['error'])) {
            Log::error('Google OAuth token error', [
                'response' => $data,
                'status' => $response->status(),
            ]);
            throw new \Exception($data['error_description'] ?? 'Failed to get access token');
        }

        return [
            'access_token' => $data['access_token'],
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null,
            'scope' => $data['scope'] ?? null,
        ];
    }

    /**
     * Get user info from Google.
     *
     * @param  string  $accessToken
     * @return array
     */
    /**
     * Get user info from Google.
     *
     * @param  string  $accessToken
     * @return array
     */
    protected function getUserInfo($accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo');

        $data = $response->json();

        if (!$response->ok()) {
            Log::error('Google user info error', [
                'response' => $data,
                'status' => $response->status(),
            ]);
            throw new \Exception($data['error']['message'] ?? 'Failed to get user info');
        }

        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'verified_email' => $data['verified_email'] ?? false,
            'picture' => $data['picture'] ?? null,
            'locale' => $data['locale'] ?? null,
        ];
    }
}
