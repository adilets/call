<?php

namespace App\Filament\Resources\Shop;

use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Filament\Resources\Concerns\AppliesRoleScope;
use App\Filament\Resources\Shop\PaymentLinkResource\Pages;
use App\Models\PaymentLink;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class PaymentLinkResource extends Resource
{
    use AppliesRoleScope;

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

                Tables\Columns\TextColumn::make('payeasy_id')
                    ->label('ID')
                    ->copyable()
                    ->toggleable()
                    ->limit(40),

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

                Tables\Columns\TextColumn::make('order.pay_method')
                    ->label('Pay Method')
                    ->formatStateUsing(fn (?string $state): string => $state ? \Illuminate\Support\Str::headline($state) : '—')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('order.total_price')
                    ->label('Total (Order Currency)')
                    ->getStateUsing(function (PaymentLink $record) {
                        $order = $record->order;
                        if (!$order) {
                            return null;
                        }

                        $currency = strtoupper($order->currency ?? 'USD');
                        $amountCents = (int) round(((float) ($order->total_price ?? 0)) * 100);

                        return Money::$currency($amountCents)->format();
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('order.paid_amount')
                    ->label('Paid Amount')
                    ->getStateUsing(function (PaymentLink $record): ?string {
                        $order = $record->order;
                        if (!$order || $order->paid_amount === null || !$order->paid_currency) {
                            return null;
                        }

                        $currency = strtoupper((string) $order->paid_currency);
                        $amountCents = (int) round(((float) $order->paid_amount) * 100);

                        return Money::$currency($amountCents)->format();
                    })
                    ->placeholder('—')
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_price_usd')
                    ->label('Total (USD)')
                    ->getStateUsing(function (PaymentLink $record) {
                        $order = $record->order;
                        if (!$order) {
                            return null;
                        }
                        $currency = strtoupper($order->currency ?? 'USD');
                        $amount = (float) ($order->total_price ?? 0);
                        $rate = (float) ($order->rate ?? 1.0);
                        if ($currency === 'USD') {
                            return Money::USD((int) round($amount * 100))->format();
                        }
                        $usdAmount = $rate > 0 ? ($amount / $rate) : $amount;
                        return Money::USD((int) round($usdAmount * 100))->format();
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
        return $query->whereHas('order', fn (Builder $orderQuery) => self::applyRoleScope($orderQuery));
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
