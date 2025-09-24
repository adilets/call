<?php

namespace App\Filament\Resources\Shop;

use App\Filament\Resources\Concerns\AppliesRoleScope;
use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Filament\Resources\Shop\OrderResource\Pages;
use App\Filament\Resources\Shop\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Forms\Components\AddressForm;
use App\Mail\PaymentLinkMail;
use App\Models\Order;
use App\Models\Customer;
use App\Models\PaymentLink;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Services\Address\UspsAddressService;
use App\Services\Email\EmailService;
use App\Services\Sms\SmsService;
use Carbon\CarbonInterface;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class OrderResource extends Resource
{
    use AppliesRoleScope;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'Shop';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make('Customer Information')
                            ->schema(static::getDetailsFormSchema())
                            ->columns(2)
                            ->collapsible()
                            ->collapsed(),

                        Forms\Components\Section::make('Order Information')
                            ->schema([
                                Forms\Components\Select::make('currency')
                                    ->label('Currency')
                                    ->options(function () {
                                        $user = Auth::user();
                                        $allowed = [];
                                        if ($user && $user->client_id) {
                                            $client = \App\Models\Client::find($user->client_id);
                                            $allowed = is_array($client?->currencies) ? $client->currencies : [];
                                        }
                                        $allowed = array_values(array_unique(array_filter($allowed)));
                                        if (empty($allowed)) {
                                            $allowed = ['USD','EUR'];
                                        }
                                        $labels = [
                                            'USD' => 'US Dollar',
                                            'EUR' => 'Euro',
                                        ];
                                        $opts = [];
                                        foreach ($allowed as $code) {
                                            $opts[$code] = $labels[$code] ?? $code;
                                        }
                                        return $opts;
                                    })
                                    ->default(function () {
                                        $user = Auth::user();
                                        if ($user && $user->client_id) {
                                            $client = \App\Models\Client::find($user->client_id);
                                            $arr = is_array($client?->currencies) ? $client->currencies : [];
                                            return $arr[0] ?? 'USD';
                                        }
                                        return 'USD';
                                    })
                                    ->searchable()
                                    ->required(),

                                Forms\Components\Select::make('shipping_method_id')
                                    ->label('Shipping method')
                                    ->required()
                                    ->options(fn () => ShippingMethod::query()->where('client_id', optional(Auth::user())->client_id)->where('enabled', true)->orderBy('name')->pluck('name', 'id'))
                        ->default(function (?Order $record) {
                            if ($record && $record->shipping_method_id) {
                                return $record->shipping_method_id;
                            }
                            $clientId = optional(Auth::user())->client_id;
                            return ShippingMethod::query()
                                ->where('client_id', $clientId)
                                ->where('enabled', true)
                                ->orderBy('name')
                                ->value('id');
                        })
                                    ->searchable()
                                    ->preload()
                                    ->afterStateUpdated(function ($state, callable $set) {
                                        $shippingPrice = ShippingMethod::find($state)?->cost ?? 0;
                                        $set('shipping_price', $shippingPrice);
                                    })
                                    ->reactive()
                                    ->afterStateUpdated(fn () => null),

                                Forms\Components\MarkdownEditor::make('notes')
                                    ->label('Notes')
                                    ->columnSpan('full'),
                            ])
                            ->columns(2),

                        Forms\Components\Section::make('Shipping Address')
                            ->schema([
                                AddressForm::make('address')
                                    ->relationship('address')
                                    ->columnSpan('full')
                                    ->disabled(fn (?Order $record) => $record !== null),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->collapsed()
                            ->hidden(fn (?Order $record) => $record === null),

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

                                        $cents = (int) round($costUsd * $rate * 100);
                                        return Money::$currency($cents)->format();
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

                                        $itemsCents = (int) round($itemsTotal * $rate * 100);
                                        return Money::$currency($itemsCents)->format();
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

                                        // Convert each part to cents and then sum to match checkout rounding
                                        $itemsCents = (int) round($itemsTotal * $rate * 100);
                                        $shipCents  = (int) round($shippingCostUsd * $rate * 100);
                                        $totalCents = $itemsCents + $shipCents;

                                        return Money::$currency($totalCents)->format();
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

                        Forms\Components\Hidden::make('order_id')
                            ->default(fn (?Order $record) => $record?->id)
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('payment_link')
                            ->label('Payment link')
                            ->default(fn (?Order $record) => $record?->payment_link)
                            ->readOnly()
                            ->extraAttributes(['x-data' => '{}'])
                            ->extraInputAttributes(['x-ref' => 'paylink'])
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('copy')
                                    ->icon('heroicon-o-clipboard')
                                    ->action(fn () => Notification::make()->title('Link copied')->success()->send())
                                    ->extraAttributes([
                                        'x-data' => '{}',
                                        'x-on:click' => new HtmlString("navigator.clipboard.writeText(\$refs.paylink ? \$refs.paylink.value : '')"),
                                    ])
                            )
                            ->hidden(fn (?Order $record) => $record?->status !== OrderStatus::New),

                        Forms\Components\Actions::make([
                            Action::make('sendSms')
                                ->label('Send SMS')
                                ->icon('heroicon-o-chat-bubble-left-right')
                                ->color('warning')
                                ->action(function (Forms\Get $get, ?Order $record) {
                                    $paymentLink = (string) ($record?->payment_link ?? $get('payment_link') ?? '');
                                    if ($paymentLink === '') {
                                        Notification::make()->title('No payment link')->danger()->send();
                                        return;
                                    }

                                    $token = (string) Str::of($paymentLink)->after('/pay/')->before('?');
                                    $link = PaymentLink::where('token', $token)->first();
                                    if (!$link) {
                                        Notification::make()->title('Payment link not found')->danger()->send();
                                        return;
                                    }

                                    $expiresAt = Carbon::parse($link->expires_at);
                                    if (now()->gte($expiresAt)) {
                                        Notification::make()->title('Payment link expired!')->danger()->send();
                                        return;
                                    }

                                    $timeLeft = now()->diffForHumans($expiresAt, [
                                        'parts'  => 2,
                                        'short'  => true,
                                        'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                                    ]);

                                    $currency = (string) ($record?->currency ?? $get('currency') ?? 'USD');
                                    $rate = (float) ($record?->rate ?? $get('rate') ?? 1);
                                    $totalUsd = (float) ($record?->total_price ?? $get('total_price') ?? 0);
                                    $amount = Money::USD((int) round($totalUsd * 100))
                                        ->convert(new Currency($currency), $rate)
                                        ->format();

                                    $customer = $record?->customer ?? Customer::find((int) ($get('customer_id') ?? 0));
                                    if (!$customer || empty($customer->phone)) {
                                        Notification::make()->title('Customer phone not set')->danger()->send();
                                        return;
                                    }

                                    $orderId = (int) ($record?->id ?? $get('order_id') ?? 0);
                                    app(SmsService::class)->send(
                                        $customer->phone,
                                        "Hi, order #OR-{$orderId} total {$amount}. Pay here: {$paymentLink} (link valid for $timeLeft)."
                                    );

                                    Notification::make()->title('SMS sent')->success()->send();
                                }),

                            Action::make('sendEmail')
                                ->label('Send Email')
                                ->icon('heroicon-o-envelope')
                                ->color('success')
                                ->visible(fn (?Order $record) => filled(optional($record?->customer)->email))
                                ->action(function (Forms\Get $get, ?Order $record) {
                                    $orderId = (int) ($record?->id ?? $get('order_id') ?? 0);
                                    if ($orderId === 0) {
                                        Notification::make()->title('Order not found')->danger()->send();
                                        return;
                                    }

                                    $order = $record ?? Order::find($orderId);
                                    if (!$order || empty(optional($order->customer)->email)) {
                                        Notification::make()->title('Customer email not set')->danger()->send();
                                        return;
                                    }

                                    app(EmailService::class)->sendMailable(
                                        $order->customer->email,
                                        new PaymentLinkMail($order)
                                    );

                                    Notification::make()->title('Email sent')->success()->send();
                                }),
                        ])
                        ->hidden(fn (?Order $record) => $record?->status !== OrderStatus::New)
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
                Tables\Columns\TextColumn::make('customer_full_name')
                    ->label('Customer')
                    ->getStateUsing(function ($record) {
                        $first = optional($record->customer)->first_name;
                        $last  = optional($record->customer)->last_name;
                        $display = trim(($first.' '.$last)) ?: optional($record->customer)->name;
                        return $display ?: null;
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('total_price')->searchable()->sortable()->money()->summarize([
                    Tables\Columns\Summarizers\Sum::make()->money(),
                ]),

                Tables\Columns\TextColumn::make('total_price_converted')
                    ->label('Total (Local)')
                    ->getStateUsing(function ($record) {
                        $currency = strtoupper($record->currency ?? 'USD');
                        $rate = (float) ($record->rate ?? 1.0);
                        // Compute items total from items to avoid double-counting shipping if stored in total_price
                        $baseUsd = (float) ($record->items()->get()->sum(function ($i) {
                            return (float) ($i->qty ?? 0) * (float) ($i->unit_price ?? 0);
                        }) ?: ($record->total_price ?? 0));

                        $shippingUsd = $record->shipping_price;

                        if ($currency === 'USD') {
                            $totalCents = (int) round(($baseUsd + $shippingUsd) * 100);
                            return Money::USD($totalCents)->format();
                        }

                        if ($currency === 'EUR') {
                            // Convert parts separately to cents, then sum
                            $subCents  = (int) round($baseUsd * $rate * 100);
                            $shipCents = (int) round($shippingUsd * $rate * 100);
                            $amountCents = $subCents + $shipCents;
                            return Money::EUR($amountCents)->format();
                        }

                        // Fallback: generic conversion like EUR case
                        $subCents  = (int) round($baseUsd * $rate * 100);
                        $shipCents = (int) round($shippingUsd * $rate * 100);
                        $amountCents = $subCents + $shipCents;
                        return Money::$currency($amountCents)->format();
                    }),

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
                    ->visible(fn ($record) => $record->status == OrderStatus::New)
                    ->modalHeading('Send payment link')
                    ->modalSubmitAction(false)
                    ->modalWidth('lg')
                    ->form([
                        Forms\Components\TextInput::make('payment_link')
                            ->label('Payment link')
                            ->default(fn ($record) => $record->payment_link)
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
                                $amount = Money::USD((int) round($record->total_price * 100))
                                    ->convert(new Currency($record->currency ?? 'USD'), $record->rate ?? 1)
                                    ->format();

                                $token = Str::of($record->payment_link)->after('/pay/')->before('?');

                                $link = PaymentLink::where('token', $token)->firstOrFail();

                                $expiresAt = Carbon::parse($link->expires_at);

                                if (now()->gte($expiresAt)) {
                                    Notification::make()->title('Payment link expired!')->danger()->send();
                                } else {
                                    $timeLeft = now()->diffForHumans($expiresAt, [
                                        'parts'  => 2,
                                        'short'  => true,
                                        'syntax' => CarbonInterface::DIFF_ABSOLUTE,
                                    ]);

                                    app(SmsService::class)->send(
                                        $record->customer->phone,
                                        "Hi, order #OR-{$record->id} total {$amount}. Pay here: {$record->payment_link} (link valid for $timeLeft)."
                                    );

                                    Notification::make()->title('SMS sent')->success()->send();
                                }
                            }),

                        TableAction::make('sendEmail')
                            ->label('Send Email')
                            ->icon('heroicon-o-envelope')
                            ->color('success')
                            ->visible(fn ($record) => filled(optional($record->customer)->email))
                            ->extraAttributes([
                                'x-data' => '{ sent: false }',
                                'x-on:click' => new HtmlString('if (!sent) { sent = true }'),
                                'x-bind:disabled' => 'sent',
                                'x-bind:class' => new HtmlString("{ 'opacity-50 cursor-not-allowed': sent }"),
                                'type' => 'button',
                                'title' => 'Send payment link via email',
                            ])
                            ->action(function ($record) {
                                app(EmailService::class)->sendMailable(
                                    $record->customer->email,
                                    new PaymentLinkMail($record)
                                );

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
            ])
            ->defaultSort('created_at', 'desc')
            ->defaultPaginationPageOption(25);
    }

    public static function getRelations(): array
    {
        return [
            // PaymentsRelationManager::class, // temporarily disabled in favor of modal action on Orders table
        ];
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
        $query = parent::getEloquentQuery()->withoutGlobalScope(SoftDeletingScope::class);

        return self::applyRoleScope($query);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['number', 'customer.first_name', 'customer.last_name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Customer' => optional($record->customer)->name,
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $query = self::applyRoleScope(Order::query());

        return (string) $query->where('status', 'new')->count();
    }

    public static function getDetailsFormSchema(): array
    {
        return [
            Forms\Components\Hidden::make('customer_id')->dehydrated(),

            Forms\Components\Select::make('customer_lookup')
                ->label('Find customer by email')
                ->placeholder('Type to search by email')
                ->searchable()
                ->dehydrated(false)
                ->columnSpan('full')
                ->afterStateHydrated(function (callable $set, ?Order $record) {
                    if ($record && $record->customer) {
                        $customer = $record->customer;
                        $set('customer_lookup', $customer->id);
                        $set('customer_id', $customer->id);
                        $set('customer_email', $customer->email);
                        $set('customer_phone', $customer->phone);
                        $set('customer_first_name', $customer->first_name);
                        $set('customer_last_name', $customer->last_name);
                    }
                })
                ->getSearchResultsUsing(function (string $search) {
                    $clientId = optional(Auth::user())->client_id;
                    if (!$clientId || trim($search) === '') {
                        return [];
                    }
                    return Customer::query()
                        ->where('client_id', $clientId)
                        ->where('email', 'like', '%' . $search . '%')
                        ->orderBy('email')
                        ->limit(20)
                        ->pluck('email', 'id')
                        ->toArray();
                })
                ->getOptionLabelUsing(fn ($value) => optional(Customer::find($value))->email)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set) {
                    $customer = Customer::find($state);
                    if ($customer) {
                        $set('customer_id', $customer->id);
                        $set('customer_email', $customer->email);
                        $set('customer_phone', $customer->phone);
                        $set('customer_first_name', $customer->first_name);
                        $set('customer_last_name', $customer->last_name);
                    }
                })
                ->helperText('Optional. Pick existing by email or type below to leave empty.'),

            Forms\Components\TextInput::make('customer_first_name')
                ->label('First name')
                ->default(fn (?Order $record) => optional(optional($record)->customer)->first_name)
                ->dehydrated(false)
                ->columnSpan(1),

            Forms\Components\TextInput::make('customer_last_name')
                ->label('Last name')
                ->default(fn (?Order $record) => optional(optional($record)->customer)->last_name)
                ->dehydrated(false)
                ->columnSpan(1),

            Forms\Components\TextInput::make('customer_email')
                ->label('Email')
                ->email()
                ->default(fn (?Order $record) => optional(optional($record)->customer)->email)
                ->dehydrated(false)
                ->reactive()
                ->columnSpan(1)
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    $selectedId = $get('customer_id');
                    if ($selectedId) {
                        $current = Customer::find($selectedId);
                        if ($current && $current->email !== $state) {
                            $set('customer_id', null);
                            $set('customer_lookup', null);
                        }
                    }
                }),

            Forms\Components\TextInput::make('customer_phone')
                ->label('Phone')
                ->default(fn (?Order $record) => optional(optional($record)->customer)->phone)
                ->dehydrated(false)
                ->columnSpan(1),

//            Forms\Components\ToggleButtons::make('status')
//                ->inline()
//                ->options(OrderStatus::class)
//                ->required(),

            // Remove billing address capture from DB – use only for payment (checkout)


        ];
    }

    public static function mutateDataWithAddressValidation(array $data): array
    {
        // Only validate US addresses
        $addr = $data['address'] ?? null;
        if (!$addr || ($addr['country'] ?? 'US') !== 'US') {
            return $data;
        }

        [$ok, $msg, $normalized] = app(UspsAddressService::class)->validateUsAddress([
            'street' => $addr['street'] ?? null,
            'city'   => $addr['city'] ?? null,
            'state'  => $addr['state'] ?? null,
            'zip'    => $addr['zip'] ?? null,
        ]);

        if (!$ok) {
            // Inject filament validation error hints across related fields
            throw \Illuminate\Validation\ValidationException::withMessages([
                'address.street' => [$msg],
                'address.city'   => [$msg],
                'address.state'  => [$msg],
                'address.zip'    => [$msg],
            ]);
        }

        $data['address']['street'] = $normalized['street'] ?? $data['address']['street'] ?? null;
        $data['address']['city']   = $normalized['city']   ?? $data['address']['city']   ?? null;
        $data['address']['state']  = $normalized['state']  ?? $data['address']['state']  ?? null;
        $data['address']['zip']    = $normalized['zip']    ?? $data['address']['zip']    ?? null;

        return $data;
    }

    public static function getItemsRepeater(): Repeater
    {
        return Repeater::make('items')
            ->relationship()
            ->schema([
                Forms\Components\Select::make('product_id')
                    ->label('Product')
                    ->options(function () {
                        $u = Auth::user();

                        return Product::query()
                            ->when($u instanceof User && method_exists($u, 'hasRole') && $u->hasRole('admin'), fn ($q) => $q)
                            ->when($u instanceof User && method_exists($u, 'hasRole') && $u->hasRole('manager'), fn ($q) => $q->where('client_id', $u->client_id))
                            ->when($u instanceof User && method_exists($u, 'hasRole') && $u->hasRole('operator'), fn ($q) =>
                            $q->where('client_id', $u->client_id)
                                ->where('user_id', $u->id)
                            )
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->toArray();
                    })
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
