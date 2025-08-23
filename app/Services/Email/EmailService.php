<?php

namespace App\Services\Email;

use App\Contracts\EmailSenderInterface;
use Illuminate\Mail\Mailable;

class EmailService
{
    public function __construct(private readonly EmailSenderInterface $sender)
    {
    }

    public function send(string $to, string $subject, string $html, ?string $fromEmail = null, ?string $fromName = null): bool
    {
        return $this->sender->send($to, $subject, $html, $fromEmail, $fromName);
    }

    public function sendMailable(string $to, Mailable $mailable): bool
    {
        $subject = $mailable->envelope()->subject ?? config('app.name');
        $html = $mailable->render();

        return $this->send($to, $subject, $html);
    }
}


