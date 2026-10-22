<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'retell' => [
        // Must be the Retell API key that has the webhook badge; it signs
        // webhooks and custom-function requests (X-Retell-Signature).
        'api_key' => env('RETELL_API_KEY'),
        'base_url' => env('RETELL_BASE_URL', 'https://api.retellai.com'),
        'from_number' => env('RETELL_FROM_NUMBER'),
        'voice_agent_id' => env('RETELL_VOICE_AGENT_ID'),
        'sms_agent_id' => env('RETELL_SMS_AGENT_ID'),
    ],

];
