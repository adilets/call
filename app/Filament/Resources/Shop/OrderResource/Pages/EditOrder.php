<?php

namespace App\Filament\Resources\Shop\OrderResource\Pages;

use App\Filament\Resources\Shop\OrderResource;
use App\Models\CurrencyRate;
use App\Models\ShippingMethod;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditOrder extends EditRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function afterSave(): void {
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
    }
}
