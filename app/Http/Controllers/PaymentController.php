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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
            if ($order && $order->status !== \App\Enums\OrderStatus::Paid) {
                $order->status = \App\Enums\OrderStatus::Expired;
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

        // Build currency set based on client settings (fallback to USD/EUR)
        $rateEur = (float) (CurrencyRate::query()
            ->where('source', 'USD')
            ->where('currency', 'EUR')
            ->value('rate') ?? 0);

        $clientCurrencies = is_array(optional($order->client)->currencies) ? $order->client->currencies : [];
        $clientCurrencies = array_values(array_unique(array_filter($clientCurrencies)));
        if (empty($clientCurrencies)) {
            $clientCurrencies = ['USD','EUR'];
        }
        $currencies = [];
        foreach ($clientCurrencies as $code) {
            if ($code === 'USD') $currencies['USD'] = 1.0;
            if ($code === 'EUR') $currencies['EUR'] = $rateEur > 0 ? $rateEur : 1.0;
        }

        $selectedCurrency = in_array($order->currency, $clientCurrencies, true)
            ? $order->currency
            : 'USD';

        $currencySymbols = [
            'USD' => '$',
            'EUR' => '€',
        ];

        $flagByCode = [ 'USD' => 'us', 'EUR' => 'eu' ];

        // Countries allowed per client (fallback to common set)
        $allowedCountryCodes = is_array(optional($order->client)->countries) ? array_values(array_unique(array_filter($order->client->countries))) : [];
        if (empty($allowedCountryCodes)) {
            $allowedCountryCodes = ['US','GB','AU','FR','DE'];
        }
        $allCountryNames = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'AU' => 'Australia',
            'FR' => 'France',
            'DE' => 'Germany',
        ];
        $countries = [];
        foreach ($allowedCountryCodes as $cc) {
            if (isset($allCountryNames[$cc])) {
                $countries[$cc] = $allCountryNames[$cc];
            }
        }

        // Region lists
        $states = config('geo.us_states');
        $gbCounties = array_values(config('geo.gb_counties') ?? []);
        $auStates = array_values(config('geo.au_states') ?? []);
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
            $allowedPayMethods = ['card', 'zelle', 'airwallex'];
        }

        return view('payment.checkout', [
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
            'deStates'         => $deStates,
            'frRegions'        => $frRegions,
            'allowedPayMethods'=> $allowedPayMethods,
        ]);
    }

    public function thanks(string $token): View {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (!$link) {
            abort(404, 'No Payment link found.');
        }

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
    public function process(string $token, Request $request, PayEasyService $payEasyService): JsonResponse|\Illuminate\Http\Response {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (!$link->isValid()) {
            $order = $link->order;
            if ($order && $order->status !== \App\Enums\OrderStatus::Paid) {
                $order->status = \App\Enums\OrderStatus::Expired;
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
            $allowedCodes = ['card', 'zelle', 'airwallex'];
        }
        $rules = [
            'email' => 'required|email|max:255',
            'currency' => 'nullable|in:USD,EUR',
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
            'pay_method' => ['nullable', Rule::in($allowedCodes)]
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

        // 6) Charge or switch to Zelle flow
        try {
            $returnUrl = route('payment.thanks', ['token' => $token]);

            $paymentResponse = $payEasyService->chargeCard($order, [
                'cardNumber' => $validated['cardNumber'],
                'firstname'  => $validated['billingFirstname'],
                'lastname'   => $validated['billingLastname'] ?? null,
                'expiry'     => $validated['expiry'],
                'cvc'        => $validated['cvc'],
                'returnUrl'  => $returnUrl,
                'email'      => $validated['email'] ?? optional($order->customer)->email,
            ]);

            if ($payMethod === 'zelle') {
                // Frontend will reveal Zelle pane and handle payment instructions
                return response()->json($paymentResponse);
            }

            if ($payMethod === 'airwallex') {
                // Instruct frontend to redirect to Airwallex page where bank details/regions are shown
                return response()->json([
                    'success' => true,
                    'requiresRedirect' => true,
                    'redirectUrl' => route('payment.airwallex', ['token' => $token]),
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
    public function airwallex(string $token, PayEasyService $payEasyService): View|\Illuminate\Http\Response
    {
        $link = PaymentLink::where('token', $token)->firstOrFail();
        if (!$link->isValid()) {
            return response()->view('payment.invalid', [], 410);
        }

        /** @var Order $order */
        $order = $link->order;

        // Use chargeCard without card to fetch bank meta from PayEasy
        $paymentResponse = [];
        try {
            $paymentResponse = $payEasyService->chargeCard($order, [
                'cardNumber' => '',
                'firstname'  => optional($order->customer)->first_name,
                'lastname'   => optional($order->customer)->last_name,
                'expiry'     => '',
                'cvc'        => '',
                'returnUrl'  => route('payment.thanks', ['token' => $token]),
                'email'      => optional($order->customer)->email,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Airwallex (meta) failed', ['error' => $e->getMessage()]);
            $paymentResponse = [];
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
            'airwallex' => $paymentResponse,
            'selectedRegion' => $selectedRegion,
            'token' => $token,
        ]);
    }
}
