<?php

namespace App\Service\WhatsApp;

final class IntentClassifier
{
    public function detect(string $message): string
    {
        $text = mb_strtolower(trim($message));

        if ($text === '') {
            return 'unknown';
        }

        if ($this->containsAny($text, [
            'reagendar',
            'cambiar cita',
            'mover cita',
            'cambiar mi cita',
            'modificar cita',
            'reprogramar',
            'cancelar cita',
        ])) {
            return 'reschedule';
        }

        if ($this->containsAny($text, [
            'barbero',
            'barberos',
            'quien corta',
            'quién corta',
            'profesional',
            'estilista',
            'quien atiende',
            'quién atiende',
        ])) {
            return 'check_barbers';
        }

        if ($this->containsAny($text, [
            'agenda',
            'cita',
            'horario',
            'horarios',
            'disponible',
            'disponibilidad',
            'reservar',
            'agendar',
            'hoy',
            'mañana',
            'manana',
        ])) {
            return 'check_agenda';
        }

        if ($this->containsAny($text, [
            'servicio',
            'servicios',
            'precio',
            'precios',
            'cuanto cuesta',
            'cuánto cuesta',
            'corte y barba',
            'barba',
            'fade',
            'desvanecido',
        ])) {
            return 'list_services';
        }

        if ($this->containsAny($text, [
            'producto',
            'productos',
            'pomada',
            'cera',
            'gel',
            'shampoo',
            'aceite',
            'fijacion',
            'fijación',
            'frizz',
            'peinar',
        ])) {
            return 'product_recommendation';
        }

        if ($this->containsAny($text, [
            'corte me queda',
            'tipo de cara',
            'cara redonda',
            'cara ovalada',
            'cara cuadrada',
            'cara alargada',
            'cara diamante',
            'cara corazón',
            'cara corazon',
            'recomienda un corte',
            'recomendar corte',
            'corte para cara',
            'que corte',
            'qué corte',
        ])) {
            return 'haircut_recommendation';
        }

        if ($this->containsAny($text, [
            'hola',
            'buenas',
            'buen día',
            'buen dia',
            'hey',
            'menu',
            'menú',
            'inicio',
        ])) {
            return 'greeting';
        }

        return 'unknown';
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }
}