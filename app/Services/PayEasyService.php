<?php

namespace App\Services;

use App\Models\CurrencyRate;
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

        $baseUsd = (float) (($order->total_price - $order->shipping_price) ?? 0);
        $shippingUsd = $order->shipping_price ?? 0.0;

        $amountUsd = $baseUsd + $shippingUsd;
        $currency = $order->currency ?? 'USD';

        $amount = $amountUsd;

        if (strtoupper($currency) == 'EUR') {
            $rate = (float) CurrencyRate::query()
                ->where('source', 'USD')
                ->where('currency', 'EUR')
                ->value('rate') ?: 1.0;

            // Match frontend logic: convert each part (items, shipping) separately, round to cents, then sum
            $subCents  = (int) round($baseUsd * max($rate, 0) * 100);
            $shipCents = (int) round($shippingUsd * max($rate, 0) * 100);
            $amountCents = $subCents + $shipCents;
            $amount = $amountCents / 100;
        }

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


