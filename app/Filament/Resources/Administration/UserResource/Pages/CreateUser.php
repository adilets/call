<?php

namespace App\Filament\Resources\Administration\UserResource\Pages;

use App\Filament\Resources\Administration\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
