<?php

namespace App\Enum;

enum AppointmentStatus: int
{
    case PENDING = 1;
    case CONFIRMED = 2;
    case CANCELLED = 3;
    case COMPLETED = 4;
    case NO_SHOW = 5;

    public function getLabel(): string
    {
        return match ($this) {
            self::PENDING => 'Pendiente',
            self::CONFIRMED => 'Confirmada',
            self::CANCELLED => 'Cancelada',
            self::COMPLETED => 'Completada',
            self::NO_SHOW => 'No asistió',
        };
    }
}
