<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Mail\PaymentConfirmationMail;
use App\Mail\PaymentLinkMail;
use App\Models\Order;
use App\Models\Customer;
use App\Models\PaymentLink;
use App\Models\ShippingMethod;
use App\Models\CurrencyRate;
use App\Services\Email\EmailService;
use App\Services\PayEasyService;
use App\Services\Sms\SmsService;
use App\Services\Phone\PhoneNormalizerService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use PragmaRX\Countries\Package\Countries;

class PaymentController extends Controller
{
    /**
     * Show the payment page.
     */
    public function show(string $token): View|\Illuminate\Http\Response {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (!$link->isValid()) {
            $order = $link->order;
            if ($order && $order->status !== OrderStatus::Paid) {
                $order->status = OrderStatus::Expired;
                $order->save();
            }
            return response()->view('payment.invalid', [], 410);
        }

        // обновляем статистику
        $link->increment('clicks');

        // если одноразовая
        if ($link->max_clicks === 1 && $link->clicks >= 1) {
            $link->used_at = now();
            $link->save();
        }

        /**
         * @var Order $order
         */
        $order = $link->order;
        $isMinimalLink = $order && $order->items()->count() === 0;

        // Build currency set based on client settings (fallback to USD/EUR)
        $clientCurrencies = is_array(optional($order->client)->currencies) ? $order->client->currencies : [];
        $clientCurrencies = array_values(array_unique(array_filter($clientCurrencies)));
        if (empty($clientCurrencies)) {
            $clientCurrencies = ['USD', 'EUR'];
        }
        $clientCurrencies = array_values(array_unique(array_map('strtoupper', $clientCurrencies)));

        $rateMap = CurrencyRate::query()
            ->where('source', 'USD')
            ->whereIn('currency', $clientCurrencies)
            ->pluck('rate', 'currency')
            ->all();

        $currencies = [];
        foreach ($clientCurrencies as $code) {
            if ($code === 'USD') {
                $currencies[$code] = 1.0;
                continue;
            }
            $rate = (float) ($rateMap[$code] ?? 0);
            $currencies[$code] = $rate > 0 ? $rate : 1.0;
        }

        $selectedCurrency = in_array($order->currency, $clientCurrencies, true)
            ? $order->currency
            : ($clientCurrencies[0] ?? 'USD');

        $moneyCurrencies = (array) config('money.currencies', []);
        $currencySymbols = [];
        foreach ($clientCurrencies as $code) {
            $symbol = $moneyCurrencies[$code]['symbol'] ?? null;
            $currencySymbols[$code] = is_string($symbol) && $symbol !== '' ? $symbol : $code;
        }

        $flagByCode = [ 'USD' => 'us', 'EUR' => 'eu' ];

        // Countries allowed per client (fallback to all)
        $allowedCountryCodes = is_array(optional($order->client)->countries)
            ? array_values(array_unique(array_filter($order->client->countries)))
            : [];
        $countriesProvider = new Countries();
        $countries = [];
        if (empty($allowedCountryCodes)) {
            $countries = $countriesProvider->all()
                ->mapWithKeys(fn ($country) => [
                    $country->cca2 => $country->name->common,
                ])
                ->sort()
                ->toArray();
        } else {
            foreach ($allowedCountryCodes as $cc) {
                $code = strtoupper((string) $cc);
                $country =
                    $countriesProvider->where('cca2', $code)->first()
                    ?? $countriesProvider->where('cca3', $code)->first()
                    ?? $countriesProvider->where('ccn3', $code)->first()
                    ?? $countriesProvider->where('name.common', $code)->first();
                if ($country?->cca2 && $country?->name?->common) {
                    $countries[$country->cca2] = $country->name->common;
                }
            }
            $countries = collect($countries)->sort()->toArray();
        }

        // Region lists
        $states = config('geo.us_states');
        $gbCounties = array_values(config('geo.gb_counties') ?? []);
        $auStates = array_values(config('geo.au_states') ?? []);
        $caProvinces = array_values(config('geo.ca_provinces') ?? []);
        $esProvinces = array_values(config('geo.es_provinces') ?? []);
        $itProvinces = array_values(config('geo.it_provinces') ?? []);
        $deStates = array_values(config('geo.de_states') ?? []);
        $frRegions = array_values(config('geo.fr_regions') ?? []);

        // Shipping methods at operator (user) level who created this order
        $shippingMethods = ShippingMethod::query()
            ->where('user_id', $order->user_id)
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        // Allowed payment methods by client (fallback to both)
        $allowedPayMethods = optional($order->client)
            ?->paymentMethods()
            ->orderBy('sort_order')
            ->pluck('code')
            ->all() ?? [];
        if (empty($allowedPayMethods)) {
            $allowedPayMethods = ['card', 'zelle', 'venmo', 'cardtousdt', 'airwallex'];
        }

        $view = $isMinimalLink ? 'payment.checkout-minimal' : 'payment.checkout';

        $fingerprintPublicKey = (string) config('services.fingerprint.public_key', '');
        $paymentError = session()->get('payment_error');

        return view($view, [
            'order'            => $order,
            'currencies'       => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'currencySymbols'  => $currencySymbols,
            'flagByCode'       => $flagByCode,
            'countries'        => $countries,
            'states'           => $states,
            'shippingMethods'  => $shippingMethods,
            'gbCounties'       => $gbCounties,
            'auStates'         => $auStates,
            'caProvinces'      => $caProvinces,
            'esProvinces'      => $esProvinces,
            'itProvinces'      => $itProvinces,
            'deStates'         => $deStates,
            'frRegions'        => $frRegions,
            'allowedPayMethods'=> $allowedPayMethods,
            'fingerprintPublicKey' => $fingerprintPublicKey,
            'paymentError'     => $paymentError,
        ]);
    }

    public function thanks(string $token): View {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (!$link) {
            abort(404, 'No Payment link found.');
        }

        $link->used_at = Carbon::now()->tz('America/New_York')->toDateTimeString();
        $link->save();

        $order = $link->order;

        if (!$order) {
            abort(404, 'No order found');
        }

        return view('payment.thanks');
    }

    /**
     * Process payment form: save currency, shipping method, billing/shipping addresses,
     * update order status, and send card data to PayEasy.
     */
    public function process(string $token, Request $request, PayEasyService $payEasyService): JsonResponse|Response {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (!$link->isValid()) {
            $order = $link->order;
            if ($order && $order->status !== OrderStatus::Paid) {
                $order->status = OrderStatus::Expired;
                $order->save();
            }
            return response()->view('payment.invalid', [], 410);
        }

        $order = $link->order;

        // 1) Validate
        $payMethod = $request->input('pay_method', 'card');

        // Determine allowed methods for this client (fallback to both)
        $allowedCodes = optional($order->client)
            ?->paymentMethods()
            ->pluck('code')
            ->all() ?? [];
        if (empty($allowedCodes)) {
            $allowedCodes = ['card', 'zelle', 'venmo', 'cardtousdt', 'airwallex'];
        }

        $clientCurrencies = is_array(optional($order->client)->currencies)
            ? array_values(array_unique(array_filter($order->client->currencies)))
            : [];
        if (empty($clientCurrencies)) {
            $clientCurrencies = ['USD', 'EUR'];
        }

        $rules = [
            'email' => 'required|email|max:255',
            'currency' => ['nullable', Rule::in($clientCurrencies)],
            'expected_amount' => 'nullable|numeric',
            'expected_currency' => 'nullable|string|max:10',
            'shipping_method_id' => 'nullable|integer|exists:shipping_methods,id',

            'billingFirstname'  => 'required|string|max:255',
            'billingLastname'  => 'nullable|string|max:255',
            'billingAddress'   => 'required|string|max:255',
            'billingCity'      => 'nullable|string|max:255',
            'billingState'     => 'nullable|string|max:255',
            'billingZip'       => 'nullable|string|max:32',
            'billingPhone'     => 'required|string|max:64',

            'shippingSame'     => 'nullable|boolean',

            'shippingFirstname' => 'nullable|string|max:255',
            'shippingLastname' => 'nullable|string|max:255',
            'shippingAddress'  => 'nullable|string|max:255',
            'shippingCity'     => 'nullable|string|max:255',
            'shippingState'    => 'nullable|string|max:255',
            'shippingZip'      => 'nullable|string|max:32',
            'shippingPhone'    => 'nullable|string|max:64',
            'pay_method' => ['nullable', Rule::in($allowedCodes)],
            'fp_visitor_id' => 'nullable|string|max:128',
            'fp_request_id' => 'nullable|string|max:128',
        ];

        // Card fields required only when paying by card
        $rules['cardNumber'] = $payMethod === 'card' ? 'required|string' : 'nullable|string';
        $rules['expiry']     = $payMethod === 'card' ? 'required|string' : 'nullable|string';
        $rules['cvc']        = $payMethod === 'card' ? 'required|string' : 'nullable|string';

        // Constrain countries to client's allowed list
        $allowedCountryCodes = is_array(optional($order->client)->countries) ? array_values(array_unique(array_filter($order->client->countries))) : [];
        if (empty($allowedCountryCodes)) {
            $allowedCountryCodes = ['US','GB','AU','FR','DE'];
        }

        $rules['billingCountry'] = ['required','string','max:3', Rule::in($allowedCountryCodes)];
        $rules['shippingCountry'] = ['nullable','string','max:3', Rule::in($allowedCountryCodes)];

        $validated = $request->validate($rules);

        // 2) Persist currency, rate & shipping method and pay method
        if (isset($validated['currency'])) {
            $order->currency = $validated['currency'];
        }

        if ($request->filled('rate')) {
            $order->rate = (float) $request->input('rate');
        }

        if (isset($validated['shipping_method_id'])) {
            $order->shipping_method_id = (int) $validated['shipping_method_id'];
        }

        if (!empty($validated['pay_method'])) {
            $order->pay_method = $validated['pay_method'];
        }

        $order->save();

        $selectedCurrency = strtoupper((string) ($validated['currency'] ?? $order->currency ?? 'USD'));
        $rate = (float) ($order->rate ?? 1.0);
        $expectedCurrency = strtoupper((string) ($validated['expected_currency'] ?? ''));
        $expectedAmount = isset($validated['expected_amount']) ? (float) $validated['expected_amount'] : null;

        if ($expectedCurrency === '' || $expectedAmount === null) {
            $expectedCurrency = in_array($payMethod, ['zelle', 'venmo'], true) ? 'USD' : $selectedCurrency;
            $expectedAmount = (float) ($order->total_price ?? 0);
            if ($expectedCurrency === 'USD' && $selectedCurrency !== 'USD') {
                $expectedAmount = $rate > 0 ? $expectedAmount / $rate : $expectedAmount;
            }
        }
        $order->expected_currency = $expectedCurrency;
        $order->expected_amount = round($expectedAmount, 2);
        $order->save();

        // 2.1) Attach or create Customer if missing
        //    - Try to find by email within same client
        //    - Update basic fields from billing if present
        if (!$order->customer_id && !empty($validated['email'])) {
            $existing = Customer::query()
                ->where('client_id', $order->client_id)
                ->where('email', $validated['email'])
                ->first();

            $billingFirst = trim((string) ($validated['billingFirstname'] ?? ''));
            $billingLast  = trim((string) ($validated['billingLastname'] ?? ''));
            $billingPhone = trim((string) ($validated['billingPhone'] ?? ''));

            if ($existing) {
                $order->customer_id = $existing->id;
                $order->save();

                $updated = false;
                if ($billingFirst !== '' && $existing->first_name !== $billingFirst) { $existing->first_name = $billingFirst; $updated = true; }
                if ($billingLast  !== '' && $existing->last_name  !== $billingLast)  { $existing->last_name  = $billingLast;  $updated = true; }
                if ($billingPhone !== '' && $existing->phone      !== $billingPhone) { $existing->phone      = $billingPhone; $updated = true; }
                if ($updated) { $existing->save(); }
            } else {
                $new = new Customer();
                $new->client_id  = $order->client_id;
                $new->user_id    = $order->user_id;
                $new->email      = $validated['email'];
                $new->first_name = $billingFirst ?: null;
                $new->last_name  = $billingLast  ?: null;
                $new->phone      = $billingPhone ?: null;
                $new->name       = trim(($new->first_name.' '.$new->last_name)) ?: null;
                $new->save();

                $order->customer_id = $new->id;
                $order->save();
            }
        }

        // 3) Billing address used only for payment – do not persist in DB anymore
        $billingData = [
            'first_name' => $validated['billingFirstname'] ?? null,
            'last_name'  => $validated['billingLastname'] ?? null,
            'country' => $validated['billingCountry'] ?? null,
            'street'  => $validated['billingAddress'] ?? null,
            'city'    => $validated['billingCity'] ?? null,
            'state'   => $validated['billingState'] ?? null,
            'zip'     => $validated['billingZip'] ?? null,
        ];

        // Update customer's first/last name and phone if changed
        $billingFirst = trim((string) ($validated['billingFirstname'] ?? ''));
        $billingLast  = trim((string) ($validated['billingLastname'] ?? ''));
        $billingPhone = trim((string) ($validated['billingPhone'] ?? ''));
        $normalizedPhone = $billingPhone !== ''
            ? app(PhoneNormalizerService::class)->normalize($billingPhone, $validated['billingCountry'] ?? 'US')
            : null;

        if ($order->customer) {
            $customer = $order->customer;
            $changed = false;
            if ($billingFirst !== '' && $customer->first_name !== $billingFirst) {
                $customer->first_name = $billingFirst;
                $changed = true;
            }

            if ($billingLast !== '' && $customer->last_name !== $billingLast) {
                $customer->last_name = $billingLast;
                $changed = true;
            }

            if ($normalizedPhone && $customer->phone !== $normalizedPhone) {
                $customer->phone = $normalizedPhone;
                $changed = true;
            }

            if ($changed) {
                $customer->save();
            }
        }

        // 4) Save SHIPPING address (only address we store)
        $shippingSame = (bool) ($validated['shippingSame'] ?? false);

        // если shippingSame=true → копируем billingData
        // иначе — только если что-то из shipping-полей было передано
        $hasShippingInput = !empty($validated['shippingAddress'])
            || !empty($validated['shippingFirstname'])
            || !empty($validated['shippingCountry'])
            || !empty($validated['shippingCity'])
            || !empty($validated['shippingState'])
            || !empty($validated['shippingZip'])
            || !empty($validated['shippingPhone']);

        if ($shippingSame || $hasShippingInput) {
            $shippingData = $shippingSame
                ? $billingData
                : [
                    'first_name' => $validated['shippingFirstname'] ?? null,
                    'last_name'  => $validated['shippingLastname'] ?? null,
                    'country' => $validated['shippingCountry'] ?? null,
                    'street'  => $validated['shippingAddress'] ?? null,
                    'city'    => $validated['shippingCity'] ?? null,
                    'state'   => $validated['shippingState'] ?? null,
                    'zip'     => $validated['shippingZip'] ?? null,
                ];

            $order->address()->updateOrCreate([], $shippingData);
        }

        // 5) Status → processing
        $order->status = OrderStatus::Processing;
        $order->save();

        // 6) Charge or redirect depending on method
        // For Airwallex, do NOT call PayEasy here — the Airwallex page will fetch bank meta.
        if ($payMethod === 'airwallex') {
            $fpVisitorId = $validated['fp_visitor_id'] ?? null;
            $fpRequestId = $validated['fp_request_id'] ?? null;

            return response()->json([
                'success' => true,
                'requiresRedirect' => true,
                'redirectUrl' => route('payment.airwallex', [
                    'token' => $token,
                    'fp_visitor_id' => $fpVisitorId,
                    'fp_request_id' => $fpRequestId,
                ]),
            ]);
        }

        try {
            $returnUrl = route('payment.thanks', ['token' => $token]);

            $chargeParams = [
                'cardNumber' => $validated['cardNumber'],
                'firstname'  => $validated['billingFirstname'],
                'lastname'   => $validated['billingLastname'] ?? null,
                'expiry'     => $validated['expiry'],
                'cvc'        => $validated['cvc'],
                'returnUrl'  => $returnUrl,
                'email'      => $validated['email'] ?? optional($order->customer)->email,
                'fp_visitor_id' => $validated['fp_visitor_id'] ?? null,
                'fp_request_id' => $validated['fp_request_id'] ?? null,
            ];

            if ($payMethod === 'cardtousdt') {
                $currency = strtoupper($order->currency ?? 'USD');
                $baseAmount = (float) (($order->total_price - $order->shipping_price) ?? 0);
                $shippingAmount = $order->shipping_price ?? 0.0;
                $amountOrder = $baseAmount + $shippingAmount;
                $expectedAmount = $amountOrder;

                if ($currency !== 'USD') {
                    $amountCurrency = $amountOrder;

                    $convertResponse = Http::timeout(20)->get(
                        'https://cardtousdt.getsecurepay.net/control/convert.php',
                        [
                            'value' => $amountCurrency,
                            'from' => strtolower($currency),
                        ]
                    );

                    $convertData = $convertResponse->ok() ? $convertResponse->json() : null;
                    $expectedAmount = is_array($convertData) && isset($convertData['value_coin'])
                        ? (float) $convertData['value_coin']
                        : null;

                    if ($expectedAmount === null) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Payment could not be processed due to failed currency conversion, please try again.',
                        ], 502);
                    }
                }

                $chargeParams['expected_amount'] = $expectedAmount;
                $chargeParams['user_agent'] = $request->userAgent();
                $chargeParams['domain'] = $request->getHost();
            }

            if (in_array($payMethod, ['zelle', 'venmo'], true)) {
                $chargeParams['amount'] = $expectedAmount;
                $chargeParams['currency'] = $expectedCurrency;
            }

            $paymentResponse = $payEasyService->chargeCard($order, $chargeParams);

            if (!empty($paymentResponse['id']) && empty($link->payeasy_id)) {
                $link->payeasy_id = (string) $paymentResponse['id'];
                $link->save();
            }

            $ipqsMessage = 'This payment method is temporarily unavailable. Please try again later or use another payment method.';
            $isIpqsBlocked = ($paymentResponse['success'] ?? null) === false
                && ($paymentResponse['status'] ?? null) === 'declined'
                && ($paymentResponse['message'] ?? '') === $ipqsMessage;

            if ($isIpqsBlocked) {
                session()->flash('payment_error', $ipqsMessage);
                return response()->json([
                    'success' => false,
                    'requiresRedirect' => true,
                    'redirectUrl' => route('payment.page', ['token' => $token]),
                ]);
            }

            if ($payMethod === 'zelle') {
                if (empty($paymentResponse['success'])) {
                    return response()->json($paymentResponse);
                }

                $cacheKey = 'zelle:' . $token;
                Cache::put($cacheKey, $paymentResponse, env('PAYMENT_LINK_TTL', 1440));

                return response()->json([
                    'success' => true,
                    'requiresRedirect' => true,
                    'redirectUrl' => route('payment.zelle', ['token' => $token]),
                ]);
            }

            if ($payMethod === 'venmo') {
                if (empty($paymentResponse['success'])) {
                    return response()->json($paymentResponse);
                }

                $cacheKey = 'venmo:' . $token;
                Cache::put($cacheKey, $paymentResponse, env('PAYMENT_LINK_TTL', 1440));

                return response()->json([
                    'success' => true,
                    'requiresRedirect' => true,
                    'redirectUrl' => route('payment.venmo', ['token' => $token]),
                ]);
            }

            if ($payMethod === 'cardtousdt') {
                if (empty($paymentResponse['success'])) {
                    return response()->json($paymentResponse);
                }

                $walletAddress = $paymentResponse['walletAddress'] ?? null;
                if (!$walletAddress) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment could not be initialized. Please try again.',
                    ], 502);
                }

                $nonce = Str::random(16);
                $callback = 'https://webhook.getsecurepay.net?' . http_build_query([
                    'order_id' => $order->id,
                    'nonce' => $nonce,
                    'p_id' => $paymentResponse['id'] ?? null,
                    'token' => $token,
                ]);

                $walletResponse = Http::timeout(20)->get('https://cardtousdt.getsecurepay.net/control/wallet.php', [
                    'address' => $walletAddress,
                    'callback' => $callback,
                ]);

                if (!$walletResponse->ok()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment could not be initialized. Please try again.',
                    ], 502);
                }

                $walletData = $walletResponse->json();
                $payAddress = is_array($walletData) ? ($walletData['address_in'] ?? null) : null;
                if (!$payAddress) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Payment could not be initialized. Please try again.',
                    ], 502);
                }

                $currency = strtoupper($order->currency ?? 'USD');
                $baseUsd = (float) (($order->total_price - $order->shipping_price) ?? 0);
                $shippingUsd = $order->shipping_price ?? 0.0;
                $amountUsd = $baseUsd + $shippingUsd;
                $amount = $amountUsd;

                if ($currency === 'EUR') {
                    $rate = (float) CurrencyRate::query()
                        ->where('source', 'USD')
                        ->where('currency', 'EUR')
                        ->value('rate') ?: 1.0;

                    $subCents  = (int) round($baseUsd * max($rate, 0) * 100);
                    $shipCents = (int) round($shippingUsd * max($rate, 0) * 100);
                    $amountCents = $subCents + $shipCents;
                    $amount = $amountCents / 100;
                }

                $amountFormatted = number_format((float) $amount, 2, '.', '');
                $email = urlencode((string) ($validated['email'] ?? optional($order->customer)->email ?? ''));

                $redirectUrl = 'https://checkout.getsecurepay.net/pay.php?' . http_build_query([
                    'address' => $payAddress,
                    'amount' => $amountFormatted,
                    'email' => $email,
                    'currency' => $currency,
                ]);

                return response()->json([
                    'success' => true,
                    'requiresRedirect' => true,
                    'redirectUrl' => $redirectUrl,
                ]);
            }

            // 3DS handling
            if (!empty($paymentResponse['redirectUrl']) && !empty($paymentResponse['transactionId'])) {
                // Frontend should redirect to this URL to complete 3DS
                return response()->json([
                    'success' => true,
                    'requiresRedirect' => true,
                    'redirectUrl' => $paymentResponse['redirectUrl'],
                    'transactionId' => $paymentResponse['transactionId'],
                ]);
            }

            $reference = $paymentResponse['id'] ?? null;
            $success   = (bool) ($paymentResponse['success'] ?? false);
            $status    = $paymentResponse['status'] ?? null;
            $message   = $paymentResponse['message'] ?? null;
            $descriptor = $paymentResponse['descriptor'] ?? null;

            if ($reference && $success) {
                $already = $order->payments()->where('reference', $reference)->exists();

                if (!$already) {
                    $order->payments()->create([
                        'reference' => $reference,
                        'provider'  => 'payeasy',
                        'method'    => 'credit_card',
                        'amount'    => (float) ($order->total_price ?? 0),
                        'currency'  => $order->currency ?? 'USD',
                    ]);
                }

                if (!empty(optional($order->customer)->email)) {
                    app(EmailService::class)->sendMailable(
                        $order->customer->email,
                        new PaymentConfirmationMail($order, $descriptor)
                    );
                }

                $amount = Money::USD((int) round($order->total_price * 100))
                    ->convert(new Currency($order->currency ?? 'USD'), $order->rate ?? 1)
                    ->format();

                $message = "Hi, we’ve received your payment for order #OR-{$order->id} ($amount). Thank you! On your bank statement the charge will appear as $descriptor.";

                app(SmsService::class)->send(
                    $order->customer->phone,
                    $message
                );

                $order->status = OrderStatus::Paid;
                $order->save();

                $link->revoked = true;
                $link->used_at = now();
                $link->save();
            } else {
                Log::warning('PayEasy failed', compact('reference','status','message'));
            }

            return response()->json($paymentResponse);

        } catch (\Throwable $e) {
            Log::error('ERROR: ' . $e->getMessage(), ['error' => $e]);
            return response()->json([
                'message' => 'Payment failed to initialize',
            ], 500);
        }
    }

    /**
     * Show Airwallex payment page (SEPA-like transfer) after order placement.
     */
    public function airwallex(string $token, PayEasyService $payEasyService): View|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if (!$link->isValid()) {
            return response()->view('payment.invalid', [], 410);
        }

        /** @var Order $order */
        $order = $link->order;

        $returnUrl = route('payment.thanks', ['token' => $token, 'pm' => 'airwallex']);

        $paymentResponse = Cache::get($token);

        if (!$paymentResponse) {
            try {
                $paymentResponse = $payEasyService->chargeCard($order, [
                    'cardNumber' => '',
                    'firstname'  => optional($order->customer)->first_name,
                    'lastname'   => optional($order->customer)->last_name,
                    'expiry'     => '',
                    'cvc'        => '',
                    'returnUrl'  => $returnUrl,
                    'email'      => optional($order->customer)->email,
                    'fp_visitor_id' => request()->input('fp_visitor_id'),
                    'fp_request_id' => request()->input('fp_request_id'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Airwallex (meta) failed', ['error' => $e->getMessage()]);
                $paymentResponse = [];
            }

            Cache::put($token, $paymentResponse, env('PAYMENT_LINK_TTL', 1440));
        }

        if (!empty($paymentResponse['reference']) && empty($link->payeasy_id)) {
            $link->payeasy_id = (string) $paymentResponse['reference'];
            $link->save();
        }

        $ipqsMessage = 'This payment method is temporarily unavailable. Please try again later or use another payment method.';
        $isIpqsBlocked = ($paymentResponse['success'] ?? null) === false
            && ($paymentResponse['status'] ?? null) === 'declined'
            && ($paymentResponse['message'] ?? '') === $ipqsMessage;

        if ($isIpqsBlocked) {
            session()->flash('payment_error', $ipqsMessage);
            return redirect()->route('payment.page', ['token' => $token]);
        }

        // Derive default region by country
        $country = strtoupper(optional($order->address)->country ?? optional($order->customer)->country ?? '');
        $selectedRegion = match ($country) {
            'US' => 'US',
            'GB' => 'UK',
            'AU' => 'AU',
            'CA' => 'CA',
            default => 'EU',
        };

        return view('payment.airwallex', [
            'order' => $order,
            'regions' => $paymentResponse,
            'selectedRegion' => $selectedRegion,
            'token' => $token,
            'referenceNumber' => $paymentResponse['reference'],
            'redirectUrl' => $returnUrl
        ]);
    }

    /**
     * Show Zelle payment page after order placement.
     */
    public function zelle(string $token): View|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if (!$link->isValid()) {
            return response()->view('payment.invalid', [], 410);
        }

        /** @var Order $order */
        $order = $link->order;

        $cacheKey = 'zelle:' . $token;
        $details = Cache::get($cacheKey);

        if (empty($details) || !is_array($details)) {
            session()->flash('payment_error', 'Zelle payment details are unavailable. Please place the order again.');
            return redirect()->route('payment.page', ['token' => $token]);
        }

        $amount = number_format((float) ($order->total_price ?? 0), 2, '.', '');

        return view('payment.zelle', [
            'order' => $order,
            'details' => $details,
            'amount' => $amount,
        ]);
    }

    /**
     * Show Venmo payment page after order placement.
     */
    public function venmo(string $token): View|\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if (!$link->isValid()) {
            return response()->view('payment.invalid', [], 410);
        }

        /** @var Order $order */
        $order = $link->order;

        $cacheKey = 'venmo:' . $token;
        $details = Cache::get($cacheKey);

        if (empty($details) || !is_array($details)) {
            session()->flash('payment_error', 'Venmo payment details are unavailable. Please place the order again.');
            return redirect()->route('payment.page', ['token' => $token]);
        }

        $amount = number_format((float) ($order->total_price ?? 0), 2, '.', '');

        return view('payment.venmo', [
            'order' => $order,
            'details' => $details,
            'amount' => $amount,
        ]);
    }
}
