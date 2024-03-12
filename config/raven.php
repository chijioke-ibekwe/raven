<?php

return [

    'default' => [
        'email' => env('EMAIL_NOTIFICATION_PROVIDER', 'sendgrid'),
        'sms' => env('SMS_NOTIFICATION_PROVIDER', 'nexmo')
    ],

    'providers' => [
        'sendgrid' => [
            'key' => env('SENDGRID_API_KEY')
        ],
        'ses' => [
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'template_source' => env('AWS_SES_TEMPLATE_SOURCE', 'sendgrid'),
            'template_directory' => env('AWS_SES_TEMPLATE_DIRECTORY', 'resources/views/emails')
        ]
    ],

    'customizations' => [
        'mail' => [
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ]
        ],
    ],

    'api' => [
        'prefix' => 'api/v1',
        'middleware' => 'api'
    ]

];