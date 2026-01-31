<?php

namespace App\Enum;

enum PaymentTypeEnum: int
{
    case TRAMSFER = 1;
    case CARD = 2;
    case CASH = 3;


    public function getLabel(): string
    {
        return match ($this) {
            self::CARD => 'Tarjeta',
            self::CASH => 'Efectivo',
            self::TRAMSFER => 'Transferencia',
        };
    }
}
