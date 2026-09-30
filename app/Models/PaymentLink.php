<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

class PaymentLink extends Model
{
    protected $fillable = [
        'order_id', 'token', 'expires_at', 'used_at',
        'revoked', 'max_clicks', 'clicks',
        'payeasy_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
        'revoked' => 'boolean',
    ];

    public static function generateForOrder($orderId, $ttlMinutes = 60, $maxClicks = null): self
    {
        $maxAttempts = 10;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return self::create([
                    'order_id' => $orderId,
                    'token' => Str::random(6),
                    'expires_at' => now()->addMinutes($ttlMinutes),
                    'max_clicks' => $maxClicks,
                ]);
            } catch (QueryException $exception) {
                if (! self::isUniqueConstraintViolation($exception) || $attempt === $maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \RuntimeException('Unable to generate unique payment link token.');
    }

    protected static function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $sqlState = (string) ($exception->errorInfo[0] ?? $exception->getCode());

        return in_array($sqlState, ['23000', '23505'], true);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isValid(): bool
    {
        return ! $this->revoked && ! $this->used_at
            && (! $this->expires_at || $this->expires_at->isFuture())
            && (! $this->max_clicks || $this->clicks < $this->max_clicks);
    }
}
