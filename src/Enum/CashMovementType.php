<?php

namespace App\Enum;

enum CashMovementType: int
{
    case INCOME = 1;
    case EXPENSE = 2;

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Ingreso',
            self::EXPENSE => 'Egreso',
        };
    }
}
