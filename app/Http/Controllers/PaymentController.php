<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Mail\PaymentConfirmationMail;
use App\Mail\PaymentLinkMail;
use App\Models\Order;
use App\Models\PaymentLink;
use App\Models\CurrencyRate;
use App\Services\Email\EmailService;
use App\Services\PayEasyService;
use App\Services\Sms\SmsService;
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
    public function show(string $token): View {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (! $link->isValid()) {
            abort(410, 'This payment link is no longer valid.');
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

        // Countries list (same source as in Order creation form)
        $countries = (new Countries())
            ->all()
            ->mapWithKeys(fn ($c) => [$c->cca2 => $c->name->common])
            ->sort()
            ->toArray();

        return view('payment.page', [
            'order'            => $order,
            'currencies'       => $currencies,
            'selectedCurrency' => $selectedCurrency,
            'currencySymbols'  => $currencySymbols,
            'flagByCode'       => $flagByCode,
            'countries'        => $countries,
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
    public function process(string $token, Request $request, PayEasyService $payEasyService): JsonResponse {
        $link = PaymentLink::where('token', $token)->firstOrFail();

        if (! $link->isValid()) {
            return response()->json(['message' => 'This payment link is no longer valid.'], 410);
        }

        $order = $link->order;

        // 1) Validate
        $validated = $request->validate([
            'currency' => 'nullable|in:USD,EUR',
            'shipping_method_id' => 'nullable|integer|exists:shipping_methods,id',

            'billingFullName'  => 'required|string|max:255',
            'billingCountry'   => 'nullable|string|max:3',
            'billingAddress'   => 'required|string|max:255',
            'billingCity'      => 'nullable|string|max:255',
            'billingState'     => 'nullable|string|max:255',
            'billingZip'       => 'nullable|string|max:32',
            'billingPhone'     => 'required|string|max:64',

            'shippingSame'     => 'nullable|boolean',

            'shippingFullName' => 'nullable|string|max:255',
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

        // 4) Save SHIPPING address
        $shippingSame = (bool) ($validated['shippingSame'] ?? false);

        // если shippingSame=true → копируем billingData
        // иначе — только если что-то из shipping-полей было передано
        $hasShippingInput = !empty($validated['shippingAddress'])
            || !empty($validated['shippingFullName'])
            || !empty($validated['shippingCountry'])
            || !empty($validated['shippingCity'])
            || !empty($validated['shippingState'])
            || !empty($validated['shippingZip'])
            || !empty($validated['shippingPhone']);

        if ($shippingSame || $hasShippingInput) {
            $shippingData = $shippingSame
                ? $billingData
                : [
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

        [$firstName, $lastName] = $this->splitFullName($validated['billingFullName'] ?? '');

        // 6) Charge
        try {
            $paymentResponse = $payEasyService->chargeCard($order, [
                'cardNumber' => $validated['cardNumber'],
                'firstname'  => $firstName,
                'lastname'   => $lastName,
                'expiry'     => $validated['expiry'],
                'cvc'        => $validated['cvc'],
                'fl_sid'     => $validated['fl_sid'],
                'frame_uuid' => $validated['frame_uuid']
            ]);

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

                app(EmailService::class)->sendMailable(
                    $order->customer->email,
                    new PaymentConfirmationMail($order, $descriptor)
                );

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

    private function splitFullName(?string $fullName): array {
        $fullName = trim((string) $fullName);

        if ($fullName === '') {
            return [null, null];
        }

        $parts = preg_split('/\s+/', $fullName);
        $first = array_shift($parts);
        $last  = count($parts) ? implode(' ', $parts) : '-';

        return [$first, $last];
    }
}
