<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ShippingMethod;
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

        $expiry = $params['expiry'] ?? '';
        $expirationDate = '';

        if ($expiry && preg_match('#^(0[1-9]|1[0-2])/(\d{2})$#', $expiry, $m)) {
            $month = $m[1];
            $year  = 2000 + (int) $m[2];
            $expirationDate = sprintf('%04d-%02d', $year, $month);
        }

        $baseAmount = (float) (($order->total_price - $order->shipping_price) ?? 0);
        $shippingAmount = $order->shipping_price ?? 0.0;
        $amountOrder = $baseAmount + $shippingAmount;
        $currency = $order->currency ?? 'USD';

        $amount = $params['amount'] ?? $amountOrder;
        $currency = $params['currency'] ?? $currency;

        // Format amount to 2 decimals (string) to meet provider expectations
        $amountCentsFinal = (int) round($amount * 100);
        $amountFormatted = number_format($amountCentsFinal / 100, 2, '.', '');

        $payload = [
            'amount' => $amountFormatted,
            'currency' => $currency,
            'ref_id' => $order->id,
            'cardNumber' => $params['cardNumber'] ?? '',
            'expirationDate' => $expirationDate,
            'cvv' => $params['cvc'] ?? '',
            'email' => $params['email'] ?? optional($order->customer)->email,
            'firstname' => $params['firstname'],
            'lastname' => $params['lastname'],
            'address1' => optional($order->address)->street,
            'city' => optional($order->address)->city,
            'state' => optional($order->address)->state,
            'zip' => optional($order->address)->zip,
            'country' => optional($order->address)->country,
            'phone' => optional($order->customer)->phone,
            'number' => $order->number,
            'ipaddress' => request()->ip(),
            'payMethod' => $order->pay_method,
            'returnUrl' => $params['returnUrl'],
            'pp' => 'cc',
            'user_agent' => $params['user_agent'] ?? request()->userAgent(),
            'domain' => $params['domain'] ?? request()->getHost(),
            'expected_amount' => $params['expected_amount'] ?? null,
            'fp_visitor_id' => $params['fp_visitor_id'] ?: null,
            'fp_request_id' => $params['fp_request_id'] ?: null,
        ];

        $payload = array_filter($payload, static fn ($v) => !is_null($v));
        $baseUrl = rtrim(config('services.payeasy.base_url', 'https://payeasy.pro'), '/');
        $clientPath = trim((string) optional($order->client)->path);

        $endpoint = $baseUrl . '/api/transactions/' . $clientPath;

        $headers = [];
        if ($token = $order->client->api_key) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        $response = Http::withHeaders($headers)->post($endpoint, $payload);

        if (!$response->successful()) {
            Log::warning('PayEasy chargeCard failed', [
                'status' => $response->status(),
                'body' => $response->json() ?? $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->json() ?? $response->body(),
            ];
        }

        return $response->json();
    }
}


