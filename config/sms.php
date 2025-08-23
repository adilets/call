<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default SMS Sender
    |--------------------------------------------------------------------------
    |
    | Здесь можно указать, какой SMS-отправитель будет использоваться по умолчанию.
    |
    */

    'default' => env('SMS_SENDER', 'mock'),

    /*
    |--------------------------------------------------------------------------
    | SMS Senders Available
    |--------------------------------------------------------------------------
    |
    | Здесь настраиваются все доступные отправители SMS.
    |
    */

    'senders' => [
        'mock' => [
            'driver' => \App\Services\Sms\Senders\MockSmsSender::class,
        ],
        'brevo' => [
            'driver' => \App\Services\Sms\Senders\BrevoSmsSender::class,
            'api_key' => env('SMS_SENDERS_BREVO_API_KEY'),
            'sender' => env('SMS_SENDERS_BREVO_SENDER'),
        ],

        'clicksend' => [
            'driver' => \App\Services\Sms\Senders\ClickSendSmsSender::class,
            'username' => env('SMS_SENDERS_CLICKSEND_USERNAME'),
            'api_key' => env('SMS_SENDERS_CLICKSEND_API_KEY'),
            'sender' => env('SMS_SENDERS_CLICKSEND_SENDER'),
        ],

//        'twilio' => [
//            'driver' => \App\Services\Sms\Senders\TwilioSmsSender::class,
//            'account_sid' => env('TWILIO_ACCOUNT_SID'),
//            'auth_token' => env('TWILIO_AUTH_TOKEN'),
//            'from' => env('TWILIO_FROM_NUMBER'),
//        ],

        // сюда можно добавить новых отправителей
    ],

    'messages' => [
        'payment' => 'Payment message link'
    ]
];
