<?php

namespace App\Enum;

enum ApplicableCommission: int
{
    case SERVICE_TYPE = 1;
    case INVENTORY_PRODUCT = 2;
    case ALL = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::SERVICE_TYPE => 'Tipo de servicio',
            self::INVENTORY_PRODUCT => 'Producto de inventariado',
            self::ALL => 'Todo',
        };
    }
}
