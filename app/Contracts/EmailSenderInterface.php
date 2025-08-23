<?php

namespace App\Contracts;

interface EmailSenderInterface
{
    /**
     * Send an email.
     */
    public function send(string $to, string $subject, string $html, ?string $fromEmail = null, ?string $fromName = null): bool;
}


