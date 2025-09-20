<?php

namespace App\Forms\Components;

use Filament\Forms;
use Filament\Forms\Get;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Closure;

class AddressForm extends Forms\Components\Field
{
    protected string $view = 'filament-forms::components.group';

    protected static array $uspsValidationCache = [];

    /** @var string|callable|null */
    public $relationship = null;

    public function relationship(string | callable $relationship): static
    {
        $this->relationship = $relationship;

        return $this;
    }

    public function saveRelationships(): void
    {
        $state = $this->getState();
        $record = $this->getRecord();
        $relationship = $record?->{$this->getRelationship()}();

        if ($relationship === null) {
            return;
        } elseif ($address = $relationship->first()) {
            $address->update($state);
        } else {
            $relationship->updateOrCreate($state);
        }

        $record?->touch();
    }

    public function getChildComponents(): array
    {
        return [
            Forms\Components\Grid::make()
                ->schema([
                    Forms\Components\Select::make('country')
                        ->label('Country')
                        ->options([
                            'US' => 'United States',
                            'GB' => 'United Kingdom',
                        ])
                        ->default('US')
                        ->required(),
                ]),

            Forms\Components\TextInput::make('street')
                ->label('Street address')
                ->maxLength(255)
                ->rules(fn (Get $get) => [function (string $attribute, $value, Closure $fail) use ($get) {
                    $result = self::validateUspsOnce($get);
                    if ($result !== true) {
                        $fail($result);
                    }
                }]),

            Forms\Components\Grid::make(3)
                ->schema([
                    Forms\Components\TextInput::make('city')
                        ->maxLength(255)
                        ->rules(fn (Get $get) => [function (string $attribute, $value, Closure $fail) use ($get) {
                            $result = self::validateUspsOnce($get);
                            if ($result !== true) {
                                $fail($result);
                            }
                        }]),
                    Forms\Components\Select::make('state')
                        ->label('State')
                        ->options(config('geo.us_states'))
                        ->searchable()
                        ->rules(fn (Get $get) => [function (string $attribute, $value, Closure $fail) use ($get) {
                            $result = self::validateUspsOnce($get);
                            if ($result !== true) {
                                $fail($result);
                            }
                        }]),
                    Forms\Components\TextInput::make('zip')
                        ->label('Zip / Postal code')
                        ->maxLength(255)
                        ->rules(fn (Get $get) => [function (string $attribute, $value, Closure $fail) use ($get) {
                            $result = self::validateUspsOnce($get);
                            if ($result !== true) {
                                $fail($result);
                            }
                        }]),
                ]),
        ];
    }

    protected static function validateUspsOnce(Get $get): string|bool
    {
        $country = $get('country') ?? 'US';
        if ($country !== 'US') {
            return true;
        }

        $street = (string) ($get('street') ?? '');
        $city   = (string) ($get('city') ?? '');
        $state  = (string) ($get('state') ?? '');
        $zip    = (string) ($get('zip') ?? '');

        $key = sha1("$street|$city|$state|$zip");
        if (array_key_exists($key, self::$uspsValidationCache)) {
            return self::$uspsValidationCache[$key];
        }

        /** @var \App\Services\Address\UspsAddressService $svc */
        $svc = App::make(\App\Services\Address\UspsAddressService::class);
        [$ok, $msg, $normalized] = $svc->validateUsAddress(compact('street','city','state','zip'));

        if ($ok) {
            self::$uspsValidationCache[$key] = true;
            return true;
        }

        $message = $msg ?: 'Invalid address.';
        self::$uspsValidationCache[$key] = $message;
        return $message;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (AddressForm $component, ?Model $record) {
            $address = $record?->getRelationValue($this->getRelationship());

            $component->state($address ? $address->toArray() : [
                'country' => 'US',
                'street' => null,
                'city' => null,
                'state' => null,
                'zip' => null,
            ]);
        });

        $this->dehydrated(false);
    }

    public function getRelationship(): string
    {
        return $this->evaluate($this->relationship) ?? $this->getName();
    }
}
