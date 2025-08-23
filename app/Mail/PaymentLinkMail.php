<?php

namespace App\Mail;

use Akaunting\Money\Currency;
use Akaunting\Money\Money;
use App\Models\Order;
use App\Models\PaymentLink;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

// use Illuminate\Contracts\Queue\ShouldQueue; // опционально, если будешь queue()

class PaymentLinkMail extends Mailable /* implements ShouldQueue */
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {}

    public function envelope(): Envelope
    {
        $orderNumber = 'OR-' . $this->order->id;

        return new Envelope(
            subject: "Payment Link for Order #{$orderNumber}",
        );
    }

    public function content(): Content
    {
        $orderNumber = 'OR-' . $this->order->id;

        $totalWithCurrency = Money::USD((int) round($this->order->total_price * 100))
            ->convert(new Currency($record->currency ?? 'USD'), $record->rate ?? 1)
            ->format();

        $items = $this->order->items?->map(fn ($i) => [
            'name'  => $i->product->name ?? ('Product #' . $i->product_id),
            'qty'   => (int) $i->qty,
            'price' => (float) $i->unit_price,
        ])->values()->all() ?? [];

        $token = Str::of($this->order->payment_link)->after('/pay/')->before('?');

        $link = PaymentLink::where('token', $token)->firstOrFail();

        if ($link) {
            $expiresAt = Carbon::parse($link->expires_at);
            $timeLeft = now()->diffForHumans($expiresAt, [
                'parts'  => 2,
                'short'  => true,
                'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            ]);
        } else {
            $timeLeft = '';
        }


        return new Content(
            view: 'emails.payment.link',
            with: [
                'orderNumber'       => $orderNumber,
                'items'             => $items,
                'totalWithCurrency' => $totalWithCurrency,
                'paymentUrl'        => $this->order->payment_link,
                'linkValidHuman'    => $timeLeft,
                'companyName'       => $this->order->user->client->company,
//                'companyContact'    => config('mail.from.address'),
//                'companySite'       => config('app.url'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
