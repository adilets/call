<?php

namespace App\Mail;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public string $descriptor,
    ) {}

    public function envelope(): Envelope
    {
        $orderNumber = $this->order->number ?: ('OR-' . $this->order->id);

        return new Envelope(
            subject: "Purchase Confirmation for Order #{$orderNumber}",
        );
    }

    public function content(): Content
    {
        $orderNumber = $this->order->number ?: ('OR-' . $this->order->id);

        $totalWithCurrency = Money::USD((int) round($this->order->total_price * 100))
            ->convert(new Currency($record->currency ?? 'USD'), $record->rate ?? 1)
            ->format();

        $items = $this->order->items?->map(function ($i) {
            return [
                'name'  => $i->product->name ?? ('Product #'.$i->product_id),
                'qty'   => (int) $i->qty,
                'price' => (float) $i->unit_price,
            ];
        })->values()->all() ?? [];

        return new Content(
            view: 'emails.payment.confirmation',
            with: [
                'orderNumber'       => $orderNumber,
                'customerName'      => optional($this->order->customer)->name,
                'items'             => $items,
                'totalWithCurrency' => $totalWithCurrency,
                'descriptor'        => $this->descriptor,
                'companyName'       => $this->order->user->client->company,
            ]
        );
    }
}
