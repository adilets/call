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
            'descriptor' => 'required|string',
            'id' => 'integer|required',
            'currency' => 'string|required',
            'amount' => 'required|numeric',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        $order->status = OrderStatus::Paid;
        $order->pay_method = 'card';
        $order->paid_amount = $validated['amount'];
        $order->paid_currency = $validated['currency'];
        $order->save();

        $order->payments()->create([
            'reference' => $validated['id'],
            'provider' => 'card',
            'method' => 'card',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
        ]);

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}
