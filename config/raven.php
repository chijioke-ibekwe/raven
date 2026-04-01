<?php

return [

    'default' => [
        'email' => env('EMAIL_NOTIFICATION_PROVIDER', 'sendgrid'),
        'sms' => env('SMS_NOTIFICATION_PROVIDER', 'vonage'),
    ],

    'providers' => [
        'sendgrid' => [
            'key' => env('SENDGRID_API_KEY'),
        ],
        'ses' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
        ],
        'vonage' => [
            'api_key' => env('VONAGE_API_KEY'),
            'api_secret' => env('VONAGE_API_SECRET'),
        ],
        'twilio' => [
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
        ],
    ],

    'customizations' => [
        'email' => [
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
        'sms' => [
            'from' => [
                'name' => env('SMS_FROM_NAME', 'Example'),
                'phone_number' => env('SMS_FROM_PHONE_NUMBER'),
            ],
        ],
        'queue_name' => env('RAVEN_QUEUE_NAME'),
        'queue_connection' => env('RAVEN_QUEUE_CONNECTION'),
        'templates_directory' => env('TEMPLATES_DIRECTORY', resource_path('templates')),
    ],

];
