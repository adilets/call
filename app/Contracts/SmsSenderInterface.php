<?php

namespace App\Contracts;

interface SmsSenderInterface
{
    public function send(string $phone, string $message): bool;
}