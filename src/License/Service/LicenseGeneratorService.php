<?php

namespace App\License\Service;

class LicenseGeneratorService
{
    /**
     * Genera una clave serial segura con formato XXXX-XXXX-XXXX-XXXX
     */
    public function generateKey(string $prefix = 'MYJ'): string
    {
        $chars = '0123456789ABCDEFGHJKLMNPQRSTUVWXYZ'; // Evitamos O, I para legibilidad
        $key = $prefix . '-';
        
        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $key .= $segment . ($i < 3 ? '-' : '');
        }

        return $key;
    }
}
