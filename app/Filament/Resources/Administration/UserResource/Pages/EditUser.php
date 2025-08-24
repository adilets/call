<?php

namespace App\Filament\Resources\Administration\UserResource\Pages;

use App\Filament\Resources\Administration\UserResource;
use Filament\Resources\Pages\EditRecord;
use Spatie\Permission\PermissionRegistrar;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected ?string $selectedRole = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Забираем роль из формы и не пишем её в таблицу users
        $this->selectedRole = $data['role'] ?? null;
        unset($data['role']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->selectedRole) {
            $this->record->syncRoles([$this->selectedRole]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }
}
