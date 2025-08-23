<?php

namespace App\Services\Sms;

use App\Contracts\SmsSenderInterface;

class SmsService
{
    protected SmsSenderInterface $smsSender;

    public function __construct(SmsSenderInterface $smsSender)
    {
        $this->smsSender = $smsSender;
    }

    public function send(string $phone, string $message): bool
    {
        return $this->smsSender->send($phone, $message);
    }
}