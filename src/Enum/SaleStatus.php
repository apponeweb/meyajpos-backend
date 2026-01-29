<?php

namespace App\Enum;

enum SaleStatus: int
{
    case PAID = 1;
    case CANCELLED = 2;
    case IN_PROGRESS = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::PAID => 'Pagada',
            self::CANCELLED => 'Cancelada',
            self::IN_PROGRESS => 'En Proceso',
        };
    }
}
