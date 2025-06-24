<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'auth_url' => 'https://accounts.google.com/o/oauth2/auth',
        'token_url' => 'https://oauth2.googleapis.com/token',
        'scopes' => ['https://www.googleapis.com/auth/userinfo.email'],
        'redirect_uri' => env('APP_URL') . '/api/credentials/oauth2/google/callback',
    ],
    'slack' => [
        'client_id' => env('SLACK_CLIENT_ID'),
        'client_secret' => env('SLACK_CLIENT_SECRET'),
        'auth_url' => 'https://slack.com/oauth/v2/authorize',
        'token_url' => 'https://slack.com/api/oauth.v2.access',
        'scopes' => ['chat:write', 'users:read'],
        'redirect_uri' => env('APP_URL') . '/api/credentials/oauth2/slack/callback',
    ]
];
