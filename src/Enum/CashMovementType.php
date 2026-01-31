<?php

namespace App\Enum;

enum CashMovementType: string
{
    case INCOME = 'INGRESO';
    case EXTRACTION = 'EXTRACCIÓN';

    public function label(): string
    {
        return match ($this) {
            self::INCOME => 'Ingreso',
            self::EXTRACTION => 'Extracción',
        };
    }
}
