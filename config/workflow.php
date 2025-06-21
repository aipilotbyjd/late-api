<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Node Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for all workflow nodes (triggers and actions).
    | Each node type should be registered here with its metadata and configuration schema.
    |
    */


    'nodes' => [
        // Slack Nodes
        'slack' => [
            'sendMessage' => [
                'name' => 'Send Slack Message',
                'description' => 'Send a message to a Slack channel',
                'category' => 'Slack',
                'icon' => 'slack',
                'fields' => [
                    'channel' => [
                        'type' => 'string',
                        'required' => true,
                        'label' => 'Channel',
                        'placeholder' => '#general or @username',
                        'help' => 'Enter a channel (e.g., #general) or username (e.g., @username)'
                    ],
                    'message' => [
                        'type' => 'textarea',
                        'required' => true,
                        'label' => 'Message',
                        'placeholder' => 'Enter your message here...',
                        'help' => 'Supports Slack markdown and emoji codes :smile:'
                    ]
                ]
            ],
            // Add more Slack actions here
        ],

        // Gmail Nodes
        'gmail' => [
            'sendEmail' => [
                'name' => 'Send Email',
                'description' => 'Send an email using Gmail',
                'category' => 'Gmail',
                'icon' => 'mail',
                'fields' => [
                    'to' => [
                        'type' => 'string',
                        'required' => true,
                        'label' => 'To',
                        'placeholder' => 'recipient@example.com',
                        'validation' => 'required|email'
                    ],
                    'subject' => [
                        'type' => 'string',
                        'required' => true,
                        'label' => 'Subject',
                        'placeholder' => 'Subject line'
                    ],
                    'body' => [
                        'type' => 'richtext',
                        'required' => true,
                        'label' => 'Message',
                        'placeholder' => 'Enter your email content here...',
                        'help' => 'Supports HTML content'
                    ]
                ]
            ],
            // Add more Gmail actions here
        ],
    ],


    /*
    |--------------------------------------------------------------------------
    | OAuth Configuration
    |--------------------------------------------------------------------------
    |
    | OAuth configuration for third-party services.
    |
    */

    'oauth' => [
        'slack' => [
            'scopes' => [
                'chat:write',
                'channels:read',
                'groups:read',
                'im:read',
                'mpim:read',
                'users:read',
            ],
            'user_scope' => [
                'chat:write',
            ],
        ],
        'google' => [
            'scopes' => [
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.compose',
                'https://www.googleapis.com/auth/userinfo.email',
                'https://www.googleapis.com/auth/userinfo.profile',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Node Categories
    |--------------------------------------------------------------------------
    |
    | Define categories for organizing nodes in the UI.
    |
    */
    'categories' => [
        'slack' => [
            'name' => 'Slack',
            'icon' => 'slack',
            'color' => '#4A154B',
        ],
        'gmail' => [
            'name' => 'Gmail',
            'icon' => 'mail',
            'color' => '#EA4335',
        ],
        // Add more categories as needed
    ],
];
