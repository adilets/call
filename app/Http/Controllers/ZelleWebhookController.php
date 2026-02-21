<?php

namespace App\Http\Controllers;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\Sms\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZelleWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
            'orderId' => 'required|integer|exists:orders,id',
            'status' => 'required|string',
            'amount' => 'required|numeric',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        $order->pay_method = 'zelle';
        $order->paid_amount = $validated['amount'];
        $order->paid_currency = 'USD';

        if ($validated['status'] == 'PARTIALLY_PAID') {
            $order->status = OrderStatus::PartiallyPaid;
        } else if ($validated['status'] == 'CAPTURED') {
            $order->status = OrderStatus::Paid;
        }

        $order->payments()->create([
            'reference' => $validated['id'],
            'provider' => 'zelle',
            'method' => 'zelle',
            'amount' => $validated['amount'],
            'currency' => 'USD',
        ]);

        $order->save();

//        $amount = Money::USD((int) round($order->total_price * 100))
//            ->convert(new Currency($order->currency ?? 'USD'), $order->rate ?? 1)
//            ->format();
//
//        $message = "Hi, we’ve received your payment for order #OR-{$order->id} ($amount) via Zelle. Thank you for your purchase!";
//
//        app(SmsService::class)->send(
//            $order->customer->phone,
//            $message
//        );

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}


