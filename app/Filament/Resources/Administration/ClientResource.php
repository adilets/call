<?php

namespace App\Filament\Resources\Administration;

use App\Filament\Resources\Administration;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use PragmaRX\Countries\Package\Countries;

class ClientResource extends Resource
{
    protected static ?string $model = Client::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = 'Administration';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('company')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('path')
                            ->required()
                            ->label('PayEasy Path')
                            ->helperText('Custom path for PayEasy endpoints, provided by the provider')
                            ->maxLength(255),

                        TextInput::make('api_key')
                            ->label('API Key')
                            ->helperText('Client API key for provider integration.')
                            ->maxLength(255),

                        Select::make('currencies')
                            ->label('Currencies')
                            ->multiple()
                            ->options(self::getCurrenciesList())
                            ->helperText('Select allowed checkout currencies.')
                            ->preload()
                            ->searchable(),

                        Select::make('countries')
                            ->label('Countries')
                            ->multiple()
                            ->options(self::getCountriesList())
                            ->helperText('Choose which countries to show in checkout (billing/shipping).')
                            ->preload()
                            ->searchable(),

                        Select::make('paymentMethods')
                            ->label('Payment Methods')
                            ->multiple()
                            ->relationship('paymentMethods', 'name')
                            ->preload()
                            ->helperText('Enable/disable checkout payment methods for this client.'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')->sortable(),
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('company')->searchable(),
                Tables\Columns\TextColumn::make('phone'),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Administration\ClientResource\Pages\ListClients::route('/'),
            'create' => Administration\ClientResource\Pages\CreateClient::route('/create'),
            'edit' => Administration\ClientResource\Pages\EditClient::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && method_exists($user, 'hasRole')
            && $user->hasRole('admin');
    }

    public static function canViewAny(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && method_exists($user, 'hasRole')
            && $user->hasRole('admin');
    }

    public static function getCountriesList(): array
    {
        $countries = new Countries();

        return $countries->all()
            ->mapWithKeys(fn ($country) => [
                $country->cca2 => $country->name->common,
            ])
            ->sort()
            ->toArray();
    }

    public static function getCurrenciesList(): array
    {
        $currencies = config('money.currencies', []);

        return collect($currencies)
            ->mapWithKeys(fn (array $meta, string $code) => [
                $code => sprintf('%s — %s', $code, $meta['name'] ?? $code),
            ])
            ->sort()
            ->toArray();
    }
}
