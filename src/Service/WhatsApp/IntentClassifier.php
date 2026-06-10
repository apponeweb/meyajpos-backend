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

        if ($this->containsAny($text, ['hola', 'buenas', 'buen día', 'buen dia', 'hey'])) {
            return 'greeting';
        }

        if ($this->containsAny($text, ['agenda', 'cita', 'horario', 'disponible', 'disponibilidad', 'reservar', 'agendar'])) {
            return 'check_agenda';
        }

        if ($this->containsAny($text, ['barbero', 'barberos', 'quien corta', 'quién corta', 'profesional'])) {
            return 'check_barbers';
        }

        if ($this->containsAny($text, ['reagendar', 'cambiar cita', 'mover cita', 'cambiar mi cita', 'modificar cita'])) {
            return 'reschedule';
        }

        if ($this->containsAny($text, ['servicio', 'servicios', 'precio', 'precios', 'cuanto cuesta', 'cuánto cuesta'])) {
            return 'list_services';
        }

        if ($this->containsAny($text, ['producto', 'pomada', 'cera', 'gel', 'shampoo', 'barba', 'aceite'])) {
            return 'product_recommendation';
        }

        if ($this->containsAny($text, ['corte me queda', 'tipo de cara', 'cara redonda', 'cara ovalada', 'recomienda un corte', 'recomendar corte'])) {
            return 'haircut_recommendation';
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