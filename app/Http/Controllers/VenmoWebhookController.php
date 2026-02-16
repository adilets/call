<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VenmoWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'orderId' => 'required|integer|exists:orders,id',
            'status' => 'required|string',
            'amount' => 'required|float',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        $order->pay_method = 'venmo';
        $order->paid_amount = $validated['amount'];
        $order->paid_currnecy = 'USD';

        if ($validated['status'] == 'PARTIALLY_PAID') {
            $order->status = OrderStatus::PartiallyPaid;
        } else if ($validated['status'] == 'CAPTURED') {
            $order->status = OrderStatus::Paid;
        }

        $order->payments()->create([
            'reference' => $validated['id'],
            'provider' => 'venmo',
            'method' => 'venmo',
            'amount' => $validated['amount'],
            'currency' => 'USD',
        ]);

        $order->save();

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}

