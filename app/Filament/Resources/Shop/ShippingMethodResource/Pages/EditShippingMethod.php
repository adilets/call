<?php

namespace App\Filament\Resources\Shop\ShippingMethodResource\Pages;

use App\Filament\Resources\Shop\ShippingMethodResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditShippingMethod extends EditRecord
{
    protected static string $resource = ShippingMethodResource::class;

    protected array $countryCodes = [];

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['country_codes'] = $this->record->countries()->pluck('country_code')->toArray();
        return $data;
    }


    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->countryCodes = $data['country_codes'] ?? [];
        unset($data['country_codes']);
        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->countries()->delete();

        foreach ($this->countryCodes as $code) {
            $this->record->countries()->create([
                'country_code' => $code,
            ]);
        }
    }
}
