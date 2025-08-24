<?php

namespace App\Filament\Resources\Administration\UserResource\Pages;

use App\Filament\Resources\Administration\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $selectedRole = null;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->selectedRole = $data['role'] ?? null;
        unset($data['role']);
        return $data;
    }

    protected function afterCreate(): void
    {
        dd('afterCreate');
        if ($this->selectedRole) {
            $this->record->syncRoles([$this->selectedRole]);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
