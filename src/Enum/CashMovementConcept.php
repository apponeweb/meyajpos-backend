<?php

namespace App\Enum;

enum CashMovementConcept: string
{
    case WITHDRAWAL = 'RETIRO';
    case PROVIDER_PAYMENT = 'PAGO_PROVEEDOR';
    case CHANGE_EXCHANGE = 'CAMBIO';
    case OTHER = 'OTRO';
    case SALE = 'VENTA';

    public function label(): string
    {
        return match ($this) {
            self::WITHDRAWAL => 'Retiro de efectivo',
            self::PROVIDER_PAYMENT => 'Pago a proveedor',
            self::CHANGE_EXCHANGE => 'Suministro para cambio',
            self::OTHER => 'Otro concepto',
            self::SALE => 'Venta',
        };
    }
}
