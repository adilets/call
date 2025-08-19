<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayEasyService
{
    /**
     * Charge card for the given order. Returns provider response.
     *
     * @param array $params
     * @throws ConnectionException
     * @return array
     */
    public function chargeCard(
        Order $order,
        array $params
    ): array
    {
        $order->loadMissing(['customer', 'address', 'client']);

        $payload = [
            'amount' => (float) ($order->total_price ?? 0),
            'currency' => $order->currency ?? 'USD',
            'ref_id' => $order->id,
            'cardNumber' => $params['cardNumber'] ?? '',
            'expirationDate' => $params['expiry'] ?? '',
            'cvv' => $params['cvc'] ?? '',
            'email' => optional($order->customer)->email,
            'firstname' => $params['firstname'],
            'lastname' => $params['lastname'],
            'address1' => optional($order->address)->street,
            'city' => optional($order->address)->city,
            'state' => optional($order->address)->state,
            'zip' => optional($order->address)->zip,
            'country' => optional($order->address)->country,
            'phone' => optional($order->customer)->phone,
            'number' => $order->number,
            'frame_uuid' => $params['frame_uuid'],
            'fl_sid' => $params['fl_sid'],
            'ipaddress' => request()->ip(),
            'pp' => 'cc'
        ];

        $payload = array_filter($payload, static fn ($v) => !is_null($v));

        $baseUrl = rtrim(config('services.payeasy.base_url', 'https://payeasy.pro'), '/');
        $clientPath = trim((string) optional($order->client)->path);

        $endpoint = $baseUrl . '/api/transactions/' . $clientPath;

        $headers = [];
        if ($token = config('services.payeasy.token')) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = Http::withHeaders($headers)->post($endpoint, $payload);

//        if (!$response->successful()) {
//            Log::warning('PayEasy chargeCard failed', [
//                'status' => $response->status(),
//                'body' => $response->json() ?? $response->body(),
//            ]);
//
//            return [
//                'success' => false,
//                'error' => $response->json() ?? $response->body(),
//            ];
//        }

        return $response->json();
    }
}


