<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    protected $fillable = [
        'order_id', 'token', 'expires_at', 'used_at',
        'revoked', 'max_clicks', 'clicks',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public static function generateForOrder($orderId, $ttlMinutes = 60, $maxClicks = null): self
    {
        return self::create([
            'order_id' => $orderId,
            'token' => Str::random(40),
            'expires_at' => now()->addMinutes($ttlMinutes),
            'max_clicks' => $maxClicks,
        ]);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isValid(): bool
    {
        return ! $this->revoked
            && (! $this->expires_at || $this->expires_at->isFuture())
            && (! $this->max_clicks || $this->clicks < $this->max_clicks);
    }
}
