<?php

return [

    'notification-service' => [
        'email' => env('EMAIL_NOTIFICATION_PROVIDER', 'sendgrid-mail'),
        'sms' => env('SMS_NOTIFICATION_PROVIDER', 'nexmo'),
        'database' => env('DATABASE_NOTIFICATION_PROVIDER', 'database')
    ],

    'api-key' => [
        'sendgrid' => env('SENDGRID_API_KEY')
    ],

    'mail' => [
        'from' => [
            'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
            'name' => env('MAIL_FROM_NAME', 'Example'),
        ]
    ],

    'api' => [
        'prefix' => 'api/v1',
        'middleware' => 'api'
    ]

];