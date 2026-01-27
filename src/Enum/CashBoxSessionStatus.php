<?php

namespace App\Enum;

enum CashBoxSessionStatus: int
{
    case OPEN = 1;
    case CLOSED = 2;
    case FORCED_CLOSED = 3;

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Abierta',
            self::CLOSED => 'Cerrada',
            self::FORCED_CLOSED => 'Cierre Forzado',
        };
    }
}
