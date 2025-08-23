<?php

namespace App\Services\Sms\Senders;

use App\Contracts\SmsSenderInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ClickSendSmsSender implements SmsSenderInterface
{
    protected string $apiUrl = 'https://rest.clicksend.com/v3/sms/send';
    protected string $username;
    protected string $apiKey;
    protected ?string $sender;

    public function __construct()
    {
        $this->username = (string) config('sms.senders.clicksend.username');
        $this->apiKey   = (string) config('sms.senders.clicksend.api_key');
        $this->sender   = config('sms.senders.clicksend.sender');
    }

    public function send(string $phone, string $message): bool
    {
        if ($this->username === '' || $this->apiKey === '') {
            Log::warning('ClickSend: missing credentials, aborting SMS send');
            return false;
        }

        $msg = array_filter([
            'source' => 'php',
            'to' => $phone,
            'body' => $message,
            'from' => $this->sender ?: null,
        ], static fn ($v) => $v !== null);

        try {
            $response = Http::withBasicAuth($this->username, $this->apiKey)
                ->asJson()
                ->post($this->apiUrl, [
                    'messages' => [ $msg ],
                ]);

            if ($response->successful()) {
                Log::info('SMS sent via ClickSend', [
                    'phone' => $phone,
                    'response' => $response->json(),
                ]);
                return true;
            }

            Log::warning('ClickSend SMS failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('ClickSend exception', ['error' => $e->getMessage()]);
            return false;
        }
    }
}


