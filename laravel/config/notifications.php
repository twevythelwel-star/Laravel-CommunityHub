<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Universal Notification System Configuration
    |--------------------------------------------------------------------------
    |
    | A completely decoupled, multi-channel notification subsystem supporting:
    | Database, Email, SMS, WhatsApp, Push, Slack, Teams, Webhooks, In-App.
    |
    */

    'enabled' => env('NOTIFICATIONS_ENABLED', true),

    'default_channels' => [
        'in_app',
        'email',
    ],

    /*
    |--------------------------------------------------------------------------
    | Active Channel Providers
    |--------------------------------------------------------------------------
    |
    | Define the default provider adapter for each channel. Any provider
    | can be dynamically swapped at runtime or via environment variables.
    |
    */
    'default_providers' => [
        'email' => env('NOTIFICATION_EMAIL_PROVIDER', 'mailgun'), // mailgun, sendgrid, postmark, ses, laravel
        'sms' => env('NOTIFICATION_SMS_PROVIDER', 'twilio'),      // twilio
        'whatsapp' => env('NOTIFICATION_WHATSAPP_PROVIDER', 'twilio'), // twilio
        'push' => env('NOTIFICATION_PUSH_PROVIDER', 'firebase'),  // firebase, onesignal
        'realtime' => env('NOTIFICATION_REALTIME_PROVIDER', 'pusher'), // pusher, ably, reverb
        'slack' => 'slack',
        'teams' => 'teams',
        'webhook' => 'webhook',
        'database' => 'database',
        'in_app' => 'in_app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Channel Fallback Map
    |--------------------------------------------------------------------------
    |
    | If delivery over a primary channel fails or recipient credentials are
    | absent, the system seamlessly cascades to configured fallback channels.
    |
    */
    'fallbacks' => [
        'whatsapp' => 'sms',
        'push' => 'in_app',
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider Configurations
    |--------------------------------------------------------------------------
    */
    'providers' => [

        // Twilio (SMS & WhatsApp)
        'twilio' => [
            'sid' => env('TWILIO_SID', 'AC_mock_twilio_account_sid'),
            'token' => env('TWILIO_TOKEN', 'mock_twilio_auth_token'),
            'from' => env('TWILIO_FROM', '+18005550199'),
            'whatsapp_from' => env('TWILIO_WHATSAPP_FROM', 'whatsapp:+14155238886'),
            'default_country_code' => env('SMS_DEFAULT_COUNTRY_CODE', '1'),
        ],

        // SendGrid (Email)
        'sendgrid' => [
            'api_key' => env('SENDGRID_API_KEY', 'SG.mock_sendgrid_key'),
            'from_address' => env('SENDGRID_FROM_ADDRESS', 'noreply@communityhub.io'),
            'from_name' => env('SENDGRID_FROM_NAME', 'Community Hub Alerts'),
        ],

        // Mailgun (Email)
        'mailgun' => [
            'domain' => env('MAILGUN_DOMAIN', 'mg.communityhub.io'),
            'secret' => env('MAILGUN_SECRET', 'key-mock-mailgun-secret'),
            'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
            'from_address' => env('MAILGUN_FROM_ADDRESS', 'notifications@communityhub.io'),
            'from_name' => env('MAILGUN_FROM_NAME', 'Community Hub Dispatcher'),
        ],

        // Postmark (Email)
        'postmark' => [
            'token' => env('POSTMARK_TOKEN', 'mock-postmark-server-token'),
            'from_address' => env('POSTMARK_FROM_ADDRESS', 'system@communityhub.io'),
            'from_name' => env('POSTMARK_FROM_NAME', 'Community Hub Postmark'),
        ],

        // Amazon SES (Email)
        'ses' => [
            'key' => env('AWS_ACCESS_KEY_ID', 'mock-aws-key'),
            'secret' => env('AWS_SECRET_ACCESS_KEY', 'mock-aws-secret'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'from_address' => env('SES_FROM_ADDRESS', 'ses@communityhub.io'),
            'from_name' => env('SES_FROM_NAME', 'Community Hub SES'),
        ],

        // Firebase Cloud Messaging (Push)
        'firebase' => [
            'server_key' => env('FCM_SERVER_KEY', 'mock-fcm-server-key'),
            'project_id' => env('FCM_PROJECT_ID', 'communityhub-fcm-project'),
        ],

        // OneSignal (Push)
        'onesignal' => [
            'app_id' => env('ONESIGNAL_APP_ID', 'mock-onesignal-app-id'),
            'rest_api_key' => env('ONESIGNAL_REST_API_KEY', 'mock-onesignal-api-key'),
        ],

        // Pusher (Real-Time In-App Broadcasting)
        'pusher' => [
            'app_id' => env('PUSHER_APP_ID', 'mock-pusher-id'),
            'key' => env('PUSHER_APP_KEY', 'mock-pusher-key'),
            'secret' => env('PUSHER_APP_SECRET', 'mock-pusher-secret'),
            'cluster' => env('PUSHER_APP_CLUSTER', 'mt1'),
        ],

        // Ably (Real-Time In-App Broadcasting)
        'ably' => [
            'key' => env('ABLY_KEY', 'mock.ably.api:secret-key'),
        ],

        // Slack (ChatOps)
        'slack' => [
            'webhook_url' => env('SLACK_WEBHOOK_URL', 'https://hooks.slack.com/services/mock/T00/B00/X00'),
            'default_channel' => env('SLACK_DEFAULT_CHANNEL', '#community-alerts'),
        ],

        // Microsoft Teams (ChatOps)
        'teams' => [
            'webhook_url' => env('TEAMS_WEBHOOK_URL', 'https://outlook.office.com/webhook/mock-guid/IncomingWebhook/mock'),
        ],

        // Outbound Signed Webhooks
        'webhook' => [
            // No default: a secret in the source signs nothing a receiver can trust.
            'signing_secret' => env('NOTIFICATION_WEBHOOK_SECRET'),
            'timeout' => 5, // seconds
            'user_agent' => 'CommunityHub-NotificationEngine/2.0',
        ],

        // Database In-App Storage
        'database' => [
            'table' => 'in_app_notifications',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Delivery Audit Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'record_deliveries' => true,
        'table' => 'notification_deliveries',
        'mask_pii' => true,
    ],
];
