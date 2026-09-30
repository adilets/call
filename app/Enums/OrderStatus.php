<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';

    case Processing = 'processing';

    case PartiallyPaid = 'partially_paid';

    case Paid = 'paid';

    case Shipped = 'shipped';

    case Delivered = 'delivered';

    case Cancelled = 'cancelled';

    case Expired = 'expired';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'New',
            self::Processing => 'Processing',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
            self::Expired => 'Expired',
        };
    }

    public function getColor(): string | array | null
    {
        return match ($this) {
            self::New => 'info',
            self::Processing => 'warning',
            self::PartiallyPaid => 'gray',
            self::Paid, self::Shipped, self::Delivered => 'success',
            self::Cancelled, self::Expired => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::New => 'heroicon-m-sparkles',
            self::Processing => 'heroicon-m-arrow-path',
            self::PartiallyPaid => 'heroicon-m-arrow-path-rounded-square',
            self::Paid => 'heroicon-m-credit-card',
            self::Shipped => 'heroicon-m-truck',
            self::Delivered => 'heroicon-m-check-badge',
            self::Cancelled => 'heroicon-m-x-circle',
            self::Expired => 'heroicon-m-clock',
        };
    }
}
