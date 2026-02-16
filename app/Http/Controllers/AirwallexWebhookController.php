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
            'currency' => 'required',
            'id' => 'required|integer',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        $order->pay_method = 'airwallex';

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

        $order->payments()->create([
            'reference' => $validated['id'],
            'provider' => 'airwallex',
            'method' => 'airwallex',
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


