<?php

namespace App\Filament\Resources\Shop\CustomerResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use PragmaRX\Countries\Package\Countries;

class AddressesRelationManager extends RelationManager
{
    protected static string $relationship = 'addresses';

    protected static ?string $recordTitleAttribute = 'full_address';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('street'),

                Forms\Components\TextInput::make('zip'),

                Forms\Components\TextInput::make('city'),

                Forms\Components\TextInput::make('state'),

                Forms\Components\Select::make('country')
                    ->searchable()
                    ->getSearchResultsUsing(function (string $query) {
                        $countries = new Countries();

                        return $countries->all()
                            ->filter(function ($country) use ($query) {
                                return str_contains(strtolower($country->name->common), strtolower($query));
                            })
                            ->mapWithKeys(function ($country) {
                                return [$country->cca2 => $country->name->common]; // cca2 = код (например, US)
                            });
                    })
                    ->getOptionLabelUsing(function ($value) {
                        $countries = new Countries();

                        return optional($countries->where('cca2', $value)->first())->name->common ?? $value;
                    })
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('street'),

                Tables\Columns\TextColumn::make('zip'),

                Tables\Columns\TextColumn::make('city'),

                Tables\Columns\TextColumn::make('country')
                    ->formatStateUsing(function ($state): ?string {
                        if (!$state) return null;

                        $countries = new Countries();

                        $country = $countries->where('cca2', strtoupper($state))->first();

                        return $country?->name->common ?? $state;
                    }),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make(),
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DetachAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->groupedBulkActions([
                Tables\Actions\DetachBulkAction::make(),
                Tables\Actions\DeleteBulkAction::make(),
            ]);
    }
}
