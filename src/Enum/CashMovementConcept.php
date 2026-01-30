<?php

namespace App\Enum;

enum CashMovementConcept: string
{
    case WITHDRAWAL = 'RETIRO';
    case PROVIDER_PAYMENT = 'PAGO A PROVEEDOR';
    case CHANGE_EXCHANGE = 'CAMBIO';
    case OTHER = 'OTRO';
    case SALE = 'VENTA';
    case OPEN_CASH_BOX = 'APERTURA DE CAJA';
    case CLOSE_CASH_BOX = 'CIERRE DE CAJA';

    public function label(): string
    {
        return match ($this) {
            self::WITHDRAWAL => 'Retiro de efectivo',
            self::PROVIDER_PAYMENT => 'Pago a proveedor',
            self::CHANGE_EXCHANGE => 'Suministro para cambio',
            self::OTHER => 'Otro concepto',
            self::SALE => 'Venta',
            self::OPEN_CASH_BOX => 'Apertura de caja',
            self::CLOSE_CASH_BOX => 'Cierre de caja',
        };
    }
}
