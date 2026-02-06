<?php

namespace App\Filament\Resources\Shop\PaymentLinkResource\Pages;

use App\Filament\Resources\Shop\PaymentLinkResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPaymentLinks extends ListRecords
{
    protected static string $resource = PaymentLinkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
