<?php

namespace App\Services;

use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;


#[AsSchedule('daily')]
class CurrencyRateService
{
    public function updateRates(): void
    {
        $response = Http::get('https://api.currencylayer.com/live', [
            'source' => 'USD',
            'access_key' => '112ba5503b82bc0e187065e052698e20'
        ]);

        if ($response->successful() && isset($response['quotes'])) {
            foreach ($response['quotes'] as $key => $rate) {
                $currency = str_replace('USD', '', $key);
                CurrencyRate::updateOrCreate(
                    ['source' => 'USD', 'currency' => $currency],
                    ['rate' => $rate]
                );
            }
        }

        Log::info('CURRENCY SUCCESSFULLY UPDATED!');
    }

    public function convertToUsd(string $currency, float $amount): ?float
    {
        $rate = CurrencyRate::where('source', 'USD')->where('currency', $currency)->value('rate');

        if ($rate) {
            $usd = $amount / $rate;

            return round($usd * 1/*0.995*/, 2); // -0.5%
        }

        return null;
    }
}
