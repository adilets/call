<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardWebhookController extends Controller {
    public function __invoke(Request $request): JsonResponse {
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
            'descriptor' => 'required|string'
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->status = OrderStatus::Paid;
        $order->save();

        $amount = Money::USD((int) round($order->total_price * 100))
            ->convert(new Currency($order->currency ?? 'USD'), $order->rate ?? 1)
            ->format();

        $message = "Hi, we’ve received your payment for order #OR-{$order->id} ($amount). Thank you! On your bank statement the charge will appear as {$validated['descriptor']}.";

        app(SmsService::class)->send(
            $order->customer->phone,
            $message
        );

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}
