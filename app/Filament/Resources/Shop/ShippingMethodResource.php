<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Shop\ShippingMethodResource\Pages\CreateShippingMethod;
use App\Filament\Resources\Shop\ShippingMethodResource\Pages\EditShippingMethod;
use App\Filament\Resources\Shop\ShippingMethodResource\Pages\ListShippingMethods;
use App\Models\ShippingMethod;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use PragmaRX\Countries\Package\Countries;

class ShippingMethodResource extends Resource
{
    protected static ?string $model = ShippingMethod::class;

    protected static ?string $navigationIcon = 'heroicon-o-truck';

    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Hidden::make('client_id')->default(fn () => auth()->user()->client_id),

            TextInput::make('name')->required(),

            Select::make('type')
                ->options([
                    'flat_rate' => 'Flat Rate',
                    'free_shipping' => 'Free Shipping',
                    'local_pickup' => 'Local Pickup',
                ])
                ->required()
                ->reactive(),

            Textarea::make('description')->rows(2),

            Select::make('country_codes')
                ->label('Available Countries')
                ->multiple()
                ->searchable()
                ->options(self::getCountriesList()),

            Toggle::make('enabled')->default(true),

            TextInput::make('cost')
                ->numeric()
                ->step(0.01)
                ->visible(fn ($get) => $get('type') === 'flat_rate')
                ->nullable(),
        ]);
    }

    /**
     * @throws \Exception
     */
    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->label('Type')->badge(),
                TextColumn::make('cost')->money('usd'),
                TextColumn::make('updated_at')->since(),
                TextColumn::make('countries')
                    ->label('Countries')
                    ->getStateUsing(fn ($record) => $record->countries->pluck('country_code')->join(', '))
                    ->wrap(),
                IconColumn::make('enabled')->boolean(),
            ])
            ->filters([
                Filter::make('enabled')->toggle(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make(),
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
            'index' => ListShippingMethods::route('/'),
            'create' => CreateShippingMethod::route('/create'),
            'edit' => EditShippingMethod::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('client_id', auth()->user()->client_id);
    }

    public static function getCountriesList(): array
    {
        $countries = new Countries();

        return collect(['ALL' => 'All countries'])
            ->merge(
                $countries->all()
                    ->mapWithKeys(fn ($country) => [
                        $country->cca2 => $country->name->common,
                    ])
                    ->sort()
            )
            ->toArray();
    }
}
