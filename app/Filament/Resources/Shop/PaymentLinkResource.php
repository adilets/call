<?php

namespace App\Filament\Resources\Shop;

use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Filament\Resources\Shop\PaymentLinkResource\Pages;
use App\Models\PaymentLink;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentLinkResource extends Resource
{
    protected static ?string $model = PaymentLink::class;

    protected static ?string $navigationIcon = 'heroicon-o-link';
    protected static ?string $navigationGroup = 'Shop';
    protected static ?string $navigationLabel = 'Payment Links';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\TextInput::make('price')
                            ->label('Price')
                            ->numeric()
                            ->step(0.01)
                            ->minValue(0.01)
                            ->required(),
                        Forms\Components\Select::make('currency')
                            ->label('Currency')
                            ->options(static::getCurrencyOptions())
                            ->default(static::getDefaultCurrency())
                            ->searchable()
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order.number')
                    ->label('Order')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(function (PaymentLink $record): string {
                        if ($record->revoked) {
                            return 'Revoked';
                        }
                        if ($record->used_at) {
                            return 'Used';
                        }
                        if ($record->expires_at && $record->expires_at->isPast()) {
                            return 'Expired';
                        }
                        if ($record->max_clicks && $record->clicks >= $record->max_clicks) {
                            return 'Expired';
                        }
                        return 'Active';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        'Used' => 'warning',
                        'Revoked' => 'danger',
                        'Expired' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('order_payment_status')
                    ->label('Payment')
                    ->badge()
                    ->getStateUsing(function (PaymentLink $record): string {
                        $orderStatus = $record->order?->status;
                        if ($orderStatus instanceof OrderStatus) {
                            return $orderStatus->getLabel();
                        }
                        return $orderStatus ? \Illuminate\Support\Str::headline((string) $orderStatus) : 'Unknown';
                    })
                    ->color(function (PaymentLink $record): ?string {
                        $orderStatus = $record->order?->status;
                        if ($orderStatus instanceof OrderStatus) {
                            return $orderStatus->getColor() ?? null;
                        }
                        return null;
                    })
                    ->icon(function (PaymentLink $record): ?string {
                        $orderStatus = $record->order?->status;
                        if ($orderStatus instanceof OrderStatus) {
                            return $orderStatus->getIcon() ?? null;
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('order.total_price')
                    ->label('Total (USD)')
                    ->money()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price_converted')
                    ->label('Total (Local)')
                    ->getStateUsing(function (PaymentLink $record) {
                        $order = $record->order;
                        if (!$order) {
                            return null;
                        }
                        $currency = strtoupper($order->currency ?? 'USD');
                        $rate = (float) ($order->rate ?? 1.0);
                        $baseUsd = (float) ($order->total_price ?? 0);

                        if ($currency === 'USD') {
                            return Money::USD((int) round($baseUsd * 100))->format();
                        }

                        $amountCents = (int) round($baseUsd * $rate * 100);
                        return Money::$currency($amountCents)->format();
                    }),

                Tables\Columns\TextColumn::make('payment_link')
                    ->label('Payment link')
                    ->getStateUsing(fn (PaymentLink $record): string => rtrim(env('PAYMENT_PAGE_BASE_URL', 'https://getsecurepay.net'), '/') . "/pay/{$record->token}")
                    ->copyable()
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc');
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
            'index' => Pages\ListPaymentLinks::route('/'),
            'create' => Pages\CreatePaymentLink::route('/create'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with('order');

        $user = Auth::user();
        if (!$user) {
            return $query->whereRaw('1=0');
        }

        if ($user instanceof User && method_exists($user, 'hasRole')) {
            if ($user->hasRole('admin')) {
                return $query;
            }

            if ($user->hasRole('manager')) {
                return $query->whereHas('order', fn (Builder $q) => $q->where('client_id', $user->client_id));
            }

            if ($user->hasRole('operator')) {
                return $query->whereHas('order', fn (Builder $q) => $q->where('user_id', $user->id));
            }

            return $query->whereRaw('1=0');
        }

        return $query->whereHas('order', fn (Builder $q) => $q->where('client_id', $user->client_id));
    }

    protected static function getCurrencyOptions(): array
    {
        $user = Auth::user();
        $allowed = [];

        if ($user && $user->client_id) {
            $client = \App\Models\Client::find($user->client_id);
            $allowed = is_array($client?->currencies) ? $client->currencies : [];
        }

        $allowed = array_values(array_unique(array_filter($allowed)));
        if (empty($allowed)) {
            $allowed = ['USD', 'EUR'];
        }

        $labels = [
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
        ];

        $options = [];
        foreach ($allowed as $code) {
            $options[$code] = $labels[$code] ?? $code;
        }

        return $options;
    }

    protected static function getDefaultCurrency(): string
    {
        $user = Auth::user();
        if ($user && $user->client_id) {
            $client = \App\Models\Client::find($user->client_id);
            $arr = is_array($client?->currencies) ? $client->currencies : [];
            return $arr[0] ?? 'USD';
        }

        return 'USD';
    }
}
