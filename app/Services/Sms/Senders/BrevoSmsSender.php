<?php

namespace App\Services\Sms\Senders;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoSmsSender implements SmsSenderInterface
{
    protected string $apiUrl = 'https://api.brevo.com/v3';
    protected string $apiKey;
    protected string $sender;

    public function __construct()
    {
        $this->apiKey = config('sms.senders.brevo.api_key');
        $this->sender = config('sms.senders.brevo.sender', 'GetSecurePay');
    }

    public function send(string $phone, string $message): bool
    {
        try {
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'accept' => 'application/json',
                'content-type' => 'application/json',
            ])->post("{$this->apiUrl}/transactionalSMS/sms", [
                'sender' => $this->sender,
                'recipient' => $phone,
                'content' => $message,
            ]);

            if ($response->successful()) {
                Log::info('SMS sent successfully via Brevo', [
                    'phone' => $phone,
                    'message' => $message,
                    'response' => $response->json()
                ]);
                return true;
            } else {
                Log::error('Failed to send SMS via Brevo', [
                    'phone' => $phone,
                    'response' => $response->body(),
                ]);
                return false;
            }
        } catch (\Throwable $e) {
            Log::error('Brevo SMS Exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
