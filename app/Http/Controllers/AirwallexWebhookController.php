<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AirwallexWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
            'status' => 'required',
            'amount' => 'required',
            'currency' => 'required'
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($validated['status'] == 'PARTIALLY_PAID') {
            $order->status = OrderStatus::PartiallyPaid;
            $order->paid_amount = (float) $validated['amount'];
            $order->paid_currency = strtoupper((string) $validated['currency']);
            $order->save();

            return response()->json([
                'success' => true,
                'orderId' => $order->id,
                'status' => $order->status->value,
                'paid_amount' => $order->paid_amount,
                'paid_currency' => $order->paid_currency,
            ]);
        }

        // Mark as paid
        $order->status = OrderStatus::Paid;
        $order->save();

        $amount = Money::USD((int) round($order->total_price * 100))
            ->convert(new Currency($order->currency ?? 'USD'), $order->rate ?? 1)
            ->format();

        $ref = 'OR-' . $order->id;
        $message = "Hi, we’ve received your bank transfer for order #$ref ($amount). Thank you!";

        if (optional($order->customer)->phone) {
            app(SmsService::class)->send(
                $order->customer->phone,
                $message
            );
        }

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}


