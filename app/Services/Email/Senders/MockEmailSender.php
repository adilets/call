<?php

namespace App\Services\Email\Senders;

use App\Contracts\EmailSenderInterface;
use Illuminate\Support\Facades\Log;

class MockEmailSender implements EmailSenderInterface
{
    public function send(string $to, string $subject, string $html, ?string $fromEmail = null, ?string $fromName = null): bool
    {
        Log::info('[Mock Email] Sending email', [
            'to' => $to,
            'subject' => $subject,
        ]);

        return true;
    }
}


