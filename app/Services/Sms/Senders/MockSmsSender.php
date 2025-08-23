<?php

namespace App\Services\Sms\Senders;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Log;

class MockSmsSender implements SmsSenderInterface
{
    public function send(string $phone, string $message): bool
    {
        Log::info('[Mock SMS] Отправка SMS', [
            'phone' => $phone,
            'message' => $message,
        ]);

        return true;
    }
}