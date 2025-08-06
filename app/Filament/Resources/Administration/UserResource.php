<?php

namespace App\Filament\Resources\Administration;

use App\Filament\Resources\Administration;
use App\Filament\Resources\Administration\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administration';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make('User Info')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->maxLength(255),

                    Select::make('client_id')
                        ->label('Client')
                        ->relationship('client', 'company')
                        ->searchable()
                        ->preload()
                        ->visible(fn () => auth()->user()->hasRole('admin')),

                    Select::make('role')
                        ->label('Role')
                        ->options(Role::all()->pluck('name', 'name'))
                        ->required()
                        ->visible(fn () => auth()->user()->hasRole('admin')),
                ])
                ->columns(2),

            Section::make('Security')
                ->schema([
                    TextInput::make('password')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->maxLength(255)
                        ->label('Password'),
                ])
                ->columns(1)
                ->collapsible(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('client.company')->label('Client'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Administration\UserResource\Pages\ListUsers::route('/'),
            'create' => Administration\UserResource\Pages\CreateUser::route('/create'),
            'edit' => Administration\UserResource\Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function afterSave(Form $form, User $record): void
    {
        if ($form->getState()['role'] ?? false) {
            $record->syncRoles([$form->getState()['role']]);
        }
    }
}
