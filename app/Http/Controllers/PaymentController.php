<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Mail\PaymentConfirmationMail;
use App\Mail\PaymentLinkMail;
use App\Models\Order;
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
use PragmaRX\Countries\Package\Countries;

class PaymentController extends Controller
{
    /**
     * Show the payment page.
     */
    public function show(string $token): View|\Illuminate\Http\Response {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (! $link->isValid()) {
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

        // Build currency set: only USD and EUR
        $rateEur = (float) (CurrencyRate::query()
            ->where('source', 'USD')
            ->where('currency', 'EUR')
            ->value('rate') ?? 0);

        $currencies = [
            'USD' => 1.0,
            'EUR' => $rateEur > 0 ? $rateEur : 1.0, // fallback to 1.0 if not present
        ];

        $selectedCurrency = in_array($order->currency, ['USD', 'EUR'], true)
            ? $order->currency
            : 'USD';

        $currencySymbols = [
            'USD' => '$',
            'EUR' => '€',
        ];

        $flagByCode = [
            'USD' => 'us',
            'EUR' => 'eu',
        ];

        // Countries list: United States and United Kingdom
        $countries = [
            'US' => 'United States',
            'GB' => 'United Kingdom',
        ];

        // Region lists
        $states = config('geo.us_states');
        $gbCounties = array_values(config('geo.gb_counties') ?? []);

        // Shipping methods at operator (user) level who created this order
        $shippingMethods = ShippingMethod::query()
            ->where('client_id', $order->client_id)
            ->where('user_id', $order->user_id)
            ->where('enabled', true)
            ->orderBy('name')
            ->get();

        return view('payment.page', [
            'order'            => $order,
            'currencies'       => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'currencySymbols'  => $currencySymbols,
            'flagByCode'       => $flagByCode,
            'countries'        => $countries,
            'states'           => $states,
            'shippingMethods'  => $shippingMethods,
            'gbCounties'       => $gbCounties,
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

        if (! $link->isValid()) {
            return response()->view('payment.invalid', [], 410);
        }

        $order = $link->order;

        // 1) Validate
        $validated = $request->validate([
            'email' => 'nullable|email|max:255',
            'currency' => 'nullable|in:USD,EUR',
            'shipping_method_id' => 'nullable|integer|exists:shipping_methods,id',

            'billingFirstname'  => 'required|string|max:255',
            'billingLastname'  => 'nullable|string|max:255',
            'billingCountry'   => 'required|string|max:3',
            'billingAddress'   => 'required|string|max:255',
            'billingCity'      => 'nullable|string|max:255',
            'billingState'     => 'nullable|string|max:255',
            'billingZip'       => 'nullable|string|max:32',
            'billingPhone'     => 'required|string|max:64',

            'shippingSame'     => 'nullable|boolean',

            'shippingFirstname' => 'nullable|string|max:255',
            'shippingLastname' => 'nullable|string|max:255',
            'shippingCountry'  => 'nullable|string|max:3',
            'shippingAddress'  => 'nullable|string|max:255',
            'shippingCity'     => 'nullable|string|max:255',
            'shippingState'    => 'nullable|string|max:255',
            'shippingZip'      => 'nullable|string|max:32',
            'shippingPhone'    => 'nullable|string|max:64',

            'cardNumber' => 'required|string',
            'expiry'     => 'required|string',
            'cvc'        => 'required|string',

            'fl_sid'     => 'required|string',
            'frame_uuid' => 'required|string',
        ]);

        // 2) Persist currency & shipping method
        if (isset($validated['currency'])) {
            $order->currency = $validated['currency'];
        }
        if (isset($validated['shipping_method_id'])) {
            $order->shipping_method_id = (int) $validated['shipping_method_id'];
        }
        $order->save();

        // 3) Save BILLING address
        $billingData = [
            'first_name' => $validated['billingFirstname'] ?? null,
            'last_name'  => $validated['billingLastname'] ?? null,
            'country' => $validated['billingCountry'] ?? null,
            'street'  => $validated['billingAddress'] ?? null,
            'city'    => $validated['billingCity'] ?? null,
            'state'   => $validated['billingState'] ?? null,
            'zip'     => $validated['billingZip'] ?? null,
        ];

        $order->addresses()->updateOrCreate(
            ['type' => 'billing'],
            $billingData
        );

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

        // 4) Save SHIPPING address
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

            $order->addresses()->updateOrCreate(
                ['type' => 'shipping'],
                $shippingData
            );
        }

        // 5) Status → processing
        $order->status = OrderStatus::Processing;
        $order->save();

        // 6) Charge
        try {
            $returnUrl = route('payment.thanks', ['token' => $token]);
            $paymentResponse = $payEasyService->chargeCard($order, [
                'cardNumber' => $validated['cardNumber'],
                'firstname'  => $validated['billingFirstname'],
                'lastname'   => $validated['billingLastname'] ?? null,
                'expiry'     => $validated['expiry'],
                'cvc'        => $validated['cvc'],
                'fl_sid'     => $validated['fl_sid'],
                'frame_uuid' => $validated['frame_uuid'],
                'returnUrl'  => $returnUrl,
                'email'      => $validated['email'] ?? optional($order->customer)->email,
            ]);

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
}
