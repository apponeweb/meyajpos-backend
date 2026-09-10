<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

final class TenantResolver
{
    private const SCHEMA_TIOSBARBER = 'tiosbarber';
    private const SCHEMA_LICENSES = 'licenses';

    public function resolve(Request $request): string
    {
        $host = strtolower($request->getHost());
        $path = strtolower($request->getPathInfo());

        /*
         * ---------------------------------------------------------
         * LICENCIAS
         * ---------------------------------------------------------
         *
         * Los endpoints de licencias se resuelven primero por path.
         *
         * /api/license/*
         *
         * También se identifica el tenant de licencias cuando la
         * petición llega mediante apilicencias.bookinpos.com.
         */
        if (
            str_starts_with($path, '/api/license/')
            || $host === 'apilicencias.bookinpos.com'
        ) {
            return self::SCHEMA_LICENSES;
        }

        /*
         * ---------------------------------------------------------
         * TIOS BARBER
         * ---------------------------------------------------------
         *
         * En desarrollo localhost y 127.0.0.1 apuntan al schema
         * tiosbarber.
         *
         * En producción api.tiosbarber.com será el dominio principal
         * del backend de Tios Barber.
         *
         * tiosbarber.bookinpos.com se conserva temporalmente por
         * compatibilidad durante la transición.
         */
        $tiosBarberHosts = [
            'localhost',
            '127.0.0.1',
            'tiosbarber.bookinpos.com',
            'api.tiosbarber.com',
        ];

        if (in_array($host, $tiosBarberHosts, true)) {
            return self::SCHEMA_TIOSBARBER;
        }

        /*
         * No permitimos caer silenciosamente en el schema public.
         *
         * Si el hostname no corresponde a un tenant conocido,
         * detenemos la petición para evitar acceso accidental
         * a información de otro cliente.
         */
        throw new \RuntimeException(
            sprintf(
                'No existe un tenant configurado para el host "%s".',
                $host
            )
        );
    }
}