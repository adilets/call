<?php

namespace App\Filament\Resources\Shop\CustomerResource\Pages;

use App\Filament\Resources\Shop\CustomerResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\Phone\PhoneNormalizerService;

class CreateCustomer extends CreateRecord
{
    protected static string $resource = CustomerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['client_id'] = auth()->user()->client_id;
        $data['user_id'] = auth()->id();

        if (!empty($data['phone'])) {
            $normalized = app(PhoneNormalizerService::class)->normalize($data['phone'], 'US');
            $data['phone'] = $normalized ?: $data['phone'];
        }

        return $data;
    }
}
