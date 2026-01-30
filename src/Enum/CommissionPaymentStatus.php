<?php

namespace App\Enum;

enum CommissionPaymentStatus: int
{
    case PENDING = 1;
    case PAID = 2;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::PAID => 'Pagada',
        };
    }
}
