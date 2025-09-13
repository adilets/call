<?php

namespace App\Filament\Resources\Shop\CustomerResource\Pages;

use App\Filament\Resources\Shop\CustomerResource;
use Filament\Actions;
use App\Services\Phone\PhoneNormalizerService;
use Filament\Resources\Pages\EditRecord;

class EditCustomer extends EditRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['phone'])) {
            $normalized = app(PhoneNormalizerService::class)->normalize($data['phone'], 'US');
            $data['phone'] = $normalized ?: $data['phone'];
        }
        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
