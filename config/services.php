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
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // SMS + WhatsApp reminders share ONE Twilio integration (same Account SID /
    // Auth Token, Messages API). SMS sends to a bare E.164 number; WhatsApp uses
    // the `whatsapp:` address prefix. Business-initiated WhatsApp reminders fall
    // outside the 24h session window, so they MUST use a pre-approved Content
    // template (content_sids below) rather than free-form text.
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_SMS_FROM'),                 // E.164, SMS sender
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),   // E.164, WhatsApp-enabled sender
        'content_sids' => [
            'upcoming' => env('TWILIO_WHATSAPP_CONTENT_UPCOMING'),
            'overdue' => env('TWILIO_WHATSAPP_CONTENT_OVERDUE'),
        ],
        // Twilio posts delivery/read status here (handled by Phase 5 webhooks).
        'status_callback' => env('TWILIO_STATUS_CALLBACK'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

];
