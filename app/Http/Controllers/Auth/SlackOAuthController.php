<?php

namespace App\Http\Controllers\Auth;

use App\Models\ConnectedAccount;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SlackOAuthController extends OAuthController
{
    /**
     * The provider name.
     *
     * @var string
     */
    protected $provider = 'slack';

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
        'chat:write',
        'chat:write.public',
        'users:read',
        'users:read.email',
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
            'client_id' => config('services.slack.client_id'),
            'redirect_uri' => route('oauth.slack.callback'),
            'scope' => implode(' ', $this->scopes),
            'user_scope' => 'identity.basic,identity.email',
            'state' => $state,
        ]);

        return redirect('https://slack.com/oauth/v2/authorize?' . $query);
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
                'name' => $userInfo['name'] ?? 'Slack User',
                'email' => $userInfo['email'] ?? null,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'expires_at' => isset($tokenData['expires_in']) ? now()->addSeconds($tokenData['expires_in']) : null,
                'metadata' => array_merge($userInfo, [
                    'team_id' => $userInfo['team_id'] ?? null,
                    'team_name' => $userInfo['team_name'] ?? null,
                    'bot_user_id' => $tokenData['bot_user_id'] ?? null,
                    'app_id' => $tokenData['app_id'] ?? null,
                ]),
            ];

            // Create or update connected account
            $account = $user->connectedAccounts()->updateOrCreate(
                ['provider' => $this->provider, 'provider_id' => $userInfo['id'] ?? null],
                $accountData
            );

            return response()->json([
                'success' => true,
                'account' => $account,
                'message' => 'Successfully connected to Slack',
            ]);
        } catch (\Exception $e) {
            Log::error('Slack OAuth Error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return response()->json([
                'error' => 'Failed to authenticate with Slack',
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
        $response = Http::asForm()->post('https://slack.com/api/oauth.v2.access', [
            'client_id' => config('services.slack.client_id'),
            'client_secret' => config('services.slack.client_secret'),
            'code' => $code,
            'redirect_uri' => route('oauth.slack.callback'),
        ]);

        $data = $response->json();

        if (!$response->ok() || !($data['ok'] ?? false)) {
            Log::error('Slack OAuth token error', [
                'response' => $data,
                'status' => $response->status(),
            ]);
            throw new \Exception($data['error'] ?? 'Failed to get access token');
        }

        return [
            'access_token' => $data['authed_user']['access_token'] ?? null,
            'refresh_token' => $data['refresh_token'] ?? null,
            'expires_in' => $data['expires_in'] ?? null,
            'token_type' => $data['token_type'] ?? null,
            'scope' => $data['scope'] ?? null,
            'bot_user_id' => $data['bot_user_id'] ?? null,
            'app_id' => $data['app_id'] ?? null,
            'team' => $data['team'] ?? null,
            'authed_user' => $data['authed_user'] ?? null,
            'bot_access_token' => $data['access_token'] ?? null, // Bot token
        ];
    }

    /**
     * Get user info from the access token.
     *
     * @param  string  $accessToken
     * @return array
     */
    protected function getUserInfo($accessToken)
    {
        $response = Http::withToken($accessToken)
            ->get('https://slack.com/api/users.identity');

        $data = $response->json();

        if (!$response->ok() || !($data['ok'] ?? false)) {
            throw new \Exception($data['error'] ?? 'Failed to fetch user info from Slack');
        }

        return [
            'id' => $data['user']['id'] ?? null,
            'name' => $data['user']['name'] ?? null,
            'email' => $data['user']['email'] ?? null,
            'team_id' => $data['team']['id'] ?? null,
            'team_name' => $data['team']['name'] ?? null,
        ];
    }
}
