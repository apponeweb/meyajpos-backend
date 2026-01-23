<?php

namespace App\Enum;

enum InventoryMovementType: int
{
    case PURCHASE_ENTRY = 1;     // Compra/Entrada
    case SALE_EXIT = 2;         // Venta/Salida
    case ADJUSTMENT_PLUS = 3;   // Ajuste+
    case ADJUSTMENT_MINUS = 4;  // Ajuste-
    case TRANSFER_EXIT = 5;     // Traspaso Salida
    case TRANSFER_ENTRY = 6;    // Traspaso Entrada
    case WASTE = 7;             // Merma

    public function getLabel(): string
    {
        return match($this) {
            self::PURCHASE_ENTRY => 'Compra/Entrada',
            self::SALE_EXIT => 'Venta/Salida',
            self::ADJUSTMENT_PLUS => 'Ajuste Positivo',
            self::ADJUSTMENT_MINUS => 'Ajuste Negativo',
            self::TRANSFER_EXIT => 'Traspaso (Salida)',
            self::TRANSFER_ENTRY => 'Traspaso (Entrada)',
            self::WASTE => 'Merma',
        };
    }
}
