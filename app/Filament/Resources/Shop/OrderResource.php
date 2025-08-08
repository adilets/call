<?php

namespace App\Filament\Resources\Shop;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Forms\Components\AddressForm;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingMethod;
use Filament\Forms;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action as TableAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make()
                            ->schema(static::getDetailsFormSchema())
                            ->columns(2),

                        Forms\Components\Section::make('Order items')
                            ->headerActions([
                                Action::make('reset')
                                    ->modalHeading('Are you sure?')
                                    ->modalDescription('All existing items will be removed from the order.')
                                    ->requiresConfirmation()
                                    ->color('danger')
                                    ->action(fn (Forms\Set $set) => $set('items', [])),
                            ])
                            ->schema([
                                static::getItemsRepeater(),
                            ]),

                        Forms\Components\Section::make('Prices')
                            ->schema([
                                Forms\Components\Placeholder::make('shipping_price')
                                    ->label('Shipping cost')
                                    ->content(function (callable $get) {
                                        $currency = $get('currency') ?? 'USD';
                                        $shippingMethodId = $get('shipping_method_id');
                                        $costUsd = ShippingMethod::find($shippingMethodId)?->cost ?? 0;

                                        $rate = app('db')->table('currency_rates')
                                            ->where('source', 'USD')
                                            ->where('currency', $currency)
                                            ->value('rate') ?? 1;

                                        return Money::$currency((int) round($costUsd * $rate * 100))->format();
                                    })
                                    ->reactive()
                                    ->live(),

                                Forms\Components\Placeholder::make('calculated_total')
                                    ->label('Item prices')
                                    ->content(function (callable $get) {
                                        $currency = $get('currency') ?? 'USD';

                                        $itemsTotal = collect($get('items') ?? [])
                                            ->sum(fn ($item) => ((float) ($item['qty'] ?? 1)) * ((float) ($item['unit_price'] ?? 0)));

                                        $rate = app('db')->table('currency_rates')
                                            ->where('source', 'USD')
                                            ->where('currency', $currency)
                                            ->value('rate') ?? 1;

                                        return Money::$currency((int) round($itemsTotal * $rate * 100))->format();
                                    })
                                    ->reactive()
                                    ->live(),

                                Forms\Components\Placeholder::make('total_price_preview')
                                    ->label('Total price')
                                    ->content(function (callable $get) {
                                        $currency = $get('currency') ?? 'USD';

                                        $itemsTotal = collect($get('items') ?? [])
                                            ->sum(fn ($item) => ((float) ($item['qty'] ?? 1)) * ((float) ($item['unit_price'] ?? 0)));

                                        $shippingCostUsd = ShippingMethod::find($get('shipping_method_id'))?->cost ?? 0;

                                        $rate = app('db')->table('currency_rates')
                                            ->where('source', 'USD')
                                            ->where('currency', $currency)
                                            ->value('rate') ?? 1;

                                        $total = ($itemsTotal + $shippingCostUsd) * $rate;

                                        return Money::$currency((int) round($total * 100))->format();
                                    })
                                    ->reactive()
                                    ->live(),

                                Forms\Components\Placeholder::make('total_price_usd')
                                    ->label('Total in USD')
                                    ->content(function (callable $get) {
                                        $itemsTotal = collect($get('items') ?? [])
                                            ->sum(fn ($item) => ((float) ($item['qty'] ?? 1)) * ((float) ($item['unit_price'] ?? 0)));

                                        $shippingCostUsd = ShippingMethod::find($get('shipping_method_id'))?->cost ?? 0;

                                        $totalUsd = $itemsTotal + $shippingCostUsd;

                                        return Money::USD((int) round($totalUsd * 100))->format();
                                    })
                                    ->reactive()
                                    ->live(),

                                Forms\Components\Hidden::make('total_price')->dehydrated()->default(0),
                                Forms\Components\Hidden::make('rate')->dehydrated(), // Для сохранения курса
                            ])
                            ->columns(4)
                    ])
                    ->columnSpan(['lg' => fn (?Order $record) => $record === null ? 3 : 2]),

                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Placeholder::make('created_at')
                            ->label('Created at')
                            ->content(fn (Order $record): ?string => $record->created_at?->diffForHumans()),

                        Forms\Components\Placeholder::make('updated_at')
                            ->label('Last modified at')
                            ->content(fn (Order $record): ?string => $record->updated_at?->diffForHumans()),
                    ])
                    ->columnSpan(['lg' => 1])
                    ->hidden(fn (?Order $record) => $record === null),
            ])
            ->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('number')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('customer.name')->searchable()->sortable()->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('total_price')->searchable()->sortable()->money()->summarize([
                    Tables\Columns\Summarizers\Sum::make()->money(),
                ]),

                Tables\Columns\TextColumn::make('total_price_converted')
                    ->label('Total (Local)')
                    ->getStateUsing(fn ($record) =>
                    Money::USD((int) round($record->total_price * 100))
                        ->convert(new Currency($record->currency ?? 'USD'), $record->rate ?? 1)
                        ->format()
                    ),

                Tables\Columns\TextColumn::make('shippingMethod.name')
                    ->label('Shipping')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Order Date')
                    ->dateTime('M j, Y H:i')
                    ->toggleable(),

            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->placeholder(fn ($state): string => 'Dec 18, ' . now()->subYear()->format('Y')),
                        Forms\Components\DatePicker::make('created_until')->placeholder(fn ($state): string => now()->format('M d, Y')),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Order from ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }
                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Order until ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }
                        return $indicators;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                TableAction::make('paymentInfo')
                    ->label('Payment')
                    ->icon('heroicon-o-currency-dollar')
                    ->modalHeading('Send payment link')
                    ->modalSubmitAction(false)
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\TextInput::make('payment_link')
                            ->label('Payment link')
                            ->default(fn ($record) => 'https://payeasy.pro/' . $record->id)
                            ->readOnly()
                            ->extraAttributes([
                                'x-data' => '{}',
                            ])
                            ->extraInputAttributes([
                                'x-ref' => 'paylink',
                            ])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('copy')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(function () {
                                        Notification::make()
                                            ->title('Link copied')
                                            ->success()
                                            ->send();
                                    })
                                    ->extraAttributes([
                                        'x-data' => '{}',
                                        'x-on:click' => new HtmlString('navigator.clipboard.writeText($refs.paylink ? $refs.paylink.value : \'\')'),
                                    ])
                            ),

                        Forms\Components\Placeholder::make('tip')
                            ->label('')
                            ->content('Choose an option below to send the payment link.'),
                    ])
                    ->extraModalFooterActions([
                        TableAction::make('sendSms')
                            ->label('Send SMS')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->color('warning')
                            ->extraAttributes([
                                'x-data' => '{ sent: false }',
                                'x-on:click' => new HtmlString('if (!sent) { sent = true }'),
                                'x-bind:disabled' => 'sent',
                                'x-bind:class' => new HtmlString("{ 'opacity-50 cursor-not-allowed': sent }"),
                                'type' => 'button',
                                'title' => 'Send payment link via SMS',
                            ])
                            ->action(function ($record) {
                                // app(\App\Services\SmsService::class)->send($record->customer_phone, "Pay order #{$record->id}: {$link}");
                                Notification::make()->title('SMS sent')->success()->send();
                            }),

                        TableAction::make('sendEmail')
                            ->label('Send Email')
                            ->icon('heroicon-o-envelope')
                            ->color('success')
                            ->extraAttributes([
                                'x-data' => '{ sent: false }',
                                'x-on:click' => new HtmlString('if (!sent) { sent = true }'),
                                'x-bind:disabled' => 'sent',
                                'x-bind:class' => new HtmlString("{ 'opacity-50 cursor-not-allowed': sent }"),
                                'type' => 'button',
                                'title' => 'Send payment link via email',
                            ])
                            ->action(function ($record) {

                                // Mail::to($record->customer_email)->send(new \App\Mail\PaymentLinkMail($record, $link));

                                Notification::make()->title('Email sent')->success()->send();
                            }),
                    ])
            ])
            ->groupedBulkActions([
                Tables\Actions\DeleteBulkAction::make()
                    ->action(function () {
                        Notification::make()
                            ->title("Now, now, don't be cheeky, leave some records for others to play with!")
                            ->warning()
                            ->send();
                    }),
            ])
            ->groups([
                Tables\Grouping\Group::make('created_at')->label('Order Date')->date()->collapsible(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'create' => Pages\CreateOrder::route('/create'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer.name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Customer' => optional($record->customer)->name,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $modelClass = static::$model;
        return (string) $modelClass::where('status', 'new')->count();
    }

    public static function getDetailsFormSchema(): array
    {
        return [
            Forms\Components\TextInput::make('number')
                ->default('OR-' . random_int(100000, 999999))
                ->disabled()
                ->dehydrated()
                ->required()
                ->maxLength(32)
                ->unique(Order::class, 'number', ignoreRecord: true),

            Forms\Components\Select::make('customer_id')
                ->relationship('customer', 'name')
                ->searchable()
                ->required()
                ->createOptionForm([
                    Forms\Components\TextInput::make('name')->required()->maxLength(255),
                    Forms\Components\TextInput::make('email')->label('Email address')->required()->email()->maxLength(255)->unique(),
                    Forms\Components\TextInput::make('phone')->maxLength(255),
                    Forms\Components\Select::make('gender')->placeholder('Select gender')->options(['male' => 'Male','female' => 'Female'])->required()->native(false),
                ])
                ->createOptionAction(fn (Action $action) => $action->modalHeading('Create customer')->modalSubmitActionLabel('Create customer')->modalWidth('lg')),

            Forms\Components\Select::make('shipping_method_id')
                ->label('Shipping method')
                ->required()
                ->options(fn () => ShippingMethod::query()->where('client_id', auth()->user()->client_id)->where('enabled', true)->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->afterStateUpdated(function ($state, callable $set) {
                    $shippingPrice = ShippingMethod::find($state)?->cost ?? 0;
                    $set('shipping_price', $shippingPrice);
                })
                ->reactive()
                ->afterStateUpdated(fn () => null),

            Forms\Components\Select::make('currency')
                ->label('Currency')
                ->searchable()
                ->options(
                    collect(config('money.currencies'))
                        ->mapWithKeys(fn ($config, $code) => [$code => $config['name']])
                        ->toArray()
                )
                ->getSearchResultsUsing(function (string $query) {
                    return collect(config('money.currencies'))
                        ->filter(fn ($config) => str_contains(strtolower($config['name']), strtolower($query)))
                        ->mapWithKeys(fn ($config, $code) => [$code => $config['name']]);
                })
                ->getOptionLabelUsing(fn ($value) => currency($value)?->getName() ?? $value)
                ->required(),

            Forms\Components\ToggleButtons::make('status')
                ->inline()
                ->options(OrderStatus::class)
                ->required(),

            AddressForm::make('address')->columnSpan('full'),

            Forms\Components\MarkdownEditor::make('notes')->columnSpan('full'),
        ];
    }

    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->options(Product::query()->pluck('name', 'id'))
                    ->required()
                    ->reactive()
                    ->afterStateUpdated(fn ($state, Forms\Set $set) => $set('unit_price', Product::find($state)?->price ?? 0))
                    ->distinct()
                    ->disableOptionsWhenSelectedInSiblingRepeaterItems()
                    ->columnSpan(['md' => 5])
                    ->searchable(),

                Forms\Components\TextInput::make('qty')
                    ->label('Quantity')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->columnSpan(['md' => 2])
                    ->reactive()
                    ->afterStateUpdated(fn () => null),

                Forms\Components\TextInput::make('unit_price')
                    ->label('Unit Price')
                    ->disabled()
                    ->dehydrated()
                    ->numeric()
                    ->required()
                    ->columnSpan(['md' => 3]),
            ])
            ->afterStateUpdated(function (callable $set, callable $get) {
                $items = $get('items') ?? [];
                $total = collect($items)->sum(fn ($item) =>
                    ((float) ($item['qty'] ?? 1)) * ((float) ($item['unit_price'] ?? 0))
                );
                $set('total_price', $total);
            })
            ->orderColumn('sort')
            ->defaultItems(1)
            ->hiddenLabel()
            ->columns(['md' => 10])
            ->required()
            ->extraItemActions([
                Action::make('openProduct')
                    ->tooltip('Open product')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (array $arguments, Repeater $component) => ProductResource::getUrl('edit', ['record' => Product::find($component->getRawItemState($arguments['item'])['product_id'])]), shouldOpenInNewTab: true)
                    ->hidden(fn (array $arguments, Repeater $component): bool => blank($component->getRawItemState($arguments['item'])['product_id'])),
            ]);
    }
}
