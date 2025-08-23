<?php

return [

    'default' => env('EMAIL_SENDER', 'mock'),

    'senders' => [
        'mock' => [
            'driver' => \App\Services\Email\Senders\MockEmailSender::class,
        ],
        'sendgrid' => [
            'driver' => \App\Services\Email\Senders\SendGridEmailSender::class,
            'api_key' => env('SENDGRID_API_KEY'),
            'from_email' => env('SENDGRID_FROM_EMAIL', 'no-reply@getsecurepay.net'),
            'from_name' => env('SENDGRID_FROM_NAME', env('APP_NAME', 'GetSecurePay')),
        ],
    ],
];


