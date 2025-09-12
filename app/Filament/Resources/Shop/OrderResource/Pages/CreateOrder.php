<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Enums\OrderStatus;
use App\Filament\Resources\Shop\OrderResource;
use App\Models\CurrencyRate;
use App\Models\PaymentLink;
use App\Models\ShippingMethod;
use App\Services\PayEasyService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Auth::user();

        if ($user) {
            $data['client_id'] = $user->client_id;
            $data['user_id'] = $user->getAuthIdentifier();
        }

        $data['status'] = OrderStatus::New;

        return $data;
    }

    protected function afterCreate(): void
    {
        $itemsTotal = $this->record->items->sum(fn ($item) =>
            ((float) ($item->qty ?? 1)) * ((float) ($item->unit_price ?? 0))
        );

        $shipping = ShippingMethod::find($this->record->shipping_method_id)?->cost ?? 0;

        $rate = (float) CurrencyRate::query()
            ->where('source', 'USD')
            ->where('currency', $this->record->currency ?? 'USD')
            ->value('rate') ?: 1.0;

        $this->record->update([
            'total_price' => $itemsTotal + $shipping,
            'shipping_price' => $shipping,
            'rate' => $rate,
        ]);

        $link = PaymentLink::generateForOrder($this->record->id, 60);

        $this->record->update([
            'number' => 'OR-' . $this->record->id,
            'payment_link' => env('PAYMENT_PAGE_BASE_URL', 'https://getsecurepay.net') . "/pay/{$link->token}",
        ]);
    }
}
