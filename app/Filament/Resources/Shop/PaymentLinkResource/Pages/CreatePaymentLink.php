<?php

namespace App\Filament\Resources\Shop\PaymentLinkResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Shop\PaymentLinkResource;
use App\Models\CurrencyRate;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\ShippingMethod;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class CreatePaymentLink extends CreateRecord
{
    protected static string $resource = PaymentLinkResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();
        if (!$user || !$user->client_id) {
            throw ValidationException::withMessages([
                'price' => ['User client is not set.'],
            ]);
        }

        $currency = strtoupper((string) ($data['currency'] ?? 'USD'));
        $price = (float) ($data['price'] ?? 0);

        $rate = 1.0;
        if ($currency !== 'USD') {
            $rate = (float) CurrencyRate::query()
                ->where('source', 'USD')
                ->where('currency', $currency)
                ->value('rate') ?: 1.0;
        }

        $shippingMethodId = ShippingMethod::query()
            ->where('client_id', $user->client_id)
            ->where('enabled', true)
            ->orderBy('name')
            ->value('id');

        if (!$shippingMethodId) {
            throw ValidationException::withMessages([
                'price' => ['No enabled shipping method found for this client.'],
            ]);
        }

        $order = Order::create([
            'client_id' => $user->client_id,
            'user_id' => $user->getAuthIdentifier(),
            'status' => OrderStatus::New,
            'currency' => $currency,
            'total_price' => round($price, 2),
            'shipping_price' => 0,
            'shipping_method_id' => $shippingMethodId,
            'rate' => $rate,
        ]);

        $link = PaymentLink::generateForOrder($order->id, (int) env('PAYMENT_LINK_TTL', 1440));

        $order->update([
            'number' => 'OR-' . $order->id,
            'payment_link' => rtrim(env('PAYMENT_PAGE_BASE_URL', 'https://getsecurepay.net'), '/') . "/pay/{$link->token}",
        ]);

        return $link;
    }
}
