<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\PaymentLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CardToUsdtWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
            'status' => 'required',
            'amount' => 'required|numeric',
            'currency' => 'required',
            'id' => 'required|integer',
        ]);

        $orderId = $validated['orderId'];

        if ($orderId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid payload.',
            ], 422);
        }

        /** @var PaymentLink|null $link */
        $link = PaymentLink::query()
            ->where('order_id', $orderId)
            ->first();

        if (!$link) {
            return response()->json([
                'success' => false,
                'message' => 'Payment link not found.',
            ], 404);
        }

        /** @var Order|null $order */
        $order = Order::query()->find($orderId);
        $order->pay_method = 'cardtousdt';
        $order->paid_amount = ($order->paid_amount ?? 0) + $validated['amount'];
        $order->paid_currency = strtoupper((string) $validated['currency']);
        $order->save();

        $status = $validated['status'];

        $reference = $request->input('id') ?? $validated['orderId'];

        $order->payments()->create([
            'reference' => $reference,
            'provider' => 'cardtousdt',
            'method' => 'crypto_onramp',
            'amount' => $validated['amount'],
            'currency' => $validated['currency'],
        ]);

        if ($status == 'PARTIALLY_PAID') {
            $order->status = OrderStatus::PartiallyPaid;
            $order->save();

            return response()->json([
                'success' => true,
                'orderId' => $order->id,
                'status' => $order->status->value,
                'paid_amount' => $order->paid_amount,
                'paid_currency' => $order->paid_currency,
            ]);
        }

        if ($status != 'CAPTURED') {
            return response()->json(['message' => 'Wrong status'], 404);
        }

        if ($order->status !== OrderStatus::Paid) {
            $order->status = OrderStatus::Paid;
            $order->save();
        }

        if (!$link->revoked) {
            $link->revoked = true;
            $link->used_at = now();
            $link->save();
        }

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
            'reference' => $reference,
        ]);
    }
}
