<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZelleWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'orderId' => 'required|integer|exists:orders,id',
        ]);

        /** @var Order|null $order */
        $order = Order::query()->find($validated['orderId']);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $order->status = OrderStatus::Paid;
        $order->save();

        return response()->json([
            'success' => true,
            'orderId' => $order->id,
            'status' => $order->status->value,
        ]);
    }
}


