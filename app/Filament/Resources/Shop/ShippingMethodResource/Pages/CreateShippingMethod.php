<?php

namespace App\Filament\Resources\Shop\ShippingMethodResource\Pages;

use App\Filament\Resources\Shop\ShippingMethodResource;
use Filament\Resources\Pages\CreateRecord;

class CreateShippingMethod extends CreateRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected array $countryCodes = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->countryCodes = $data['country_codes'] ?? [];
        unset($data['country_codes']);
        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->countries()->delete();

        foreach ($this->countryCodes as $code) {
            $this->record->countries()->create([
                'country_code' => $code,
            ]);
        }
    }
}
