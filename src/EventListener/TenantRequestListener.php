<?php

namespace App\EventListener;

use App\Service\TenantContext;
use App\Service\TenantResolver;
use Doctrine\DBAL\Connection;
use Symfony\Component\HttpKernel\Event\RequestEvent;

final class TenantRequestListener
{
    private const ALLOWED_SCHEMAS = [
        'tiosbarber',
        'licenses',
    ];

    public function __construct(
        private readonly Connection $connection,
        private readonly TenantResolver $tenantResolver,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        /*
         * Solo trabajamos con la petición principal.
         */
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        /*
         * Symfony profiler y assets internos no requieren tenant.
         */
        $path = $request->getPathInfo();

        if (
            str_starts_with($path, '/_profiler')
            || str_starts_with($path, '/_wdt')
        ) {
            return;
        }

        /*
         * Determinamos qué tenant corresponde a la petición.
         */
        $schema = $this->tenantResolver->resolve($request);

        /*
         * Defensa adicional.
         *
         * Nunca utilizamos directamente un schema arbitrario
         * recibido del navegador.
         */
        if (!in_array($schema, self::ALLOWED_SCHEMAS, true)) {
            throw new \RuntimeException(
                sprintf(
                    'El schema "%s" no está autorizado.',
                    $schema
                )
            );
        }

        /*
         * Verificamos que realmente exista en PostgreSQL.
         */
        $exists = (bool) $this->connection->fetchOne(
            '
                SELECT EXISTS (
                    SELECT 1
                    FROM pg_namespace
                    WHERE nspname = ?
                )
            ',
            [$schema]
        );

        if (!$exists) {
            throw new \RuntimeException(
                sprintf(
                    'El schema PostgreSQL "%s" no existe.',
                    $schema
                )
            );
        }

        /*
         * IMPORTANTE:
         *
         * set_config se ejecuta sobre LA MISMA conexión Doctrine
         * que posteriormente utilizarán Security, repositories,
         * controllers y services.
         */
        $this->connection->fetchOne(
            "SELECT set_config('search_path', ?, false)",
            [$schema . ', public']
        );

        /*
         * Verificación defensiva.
         */
        $currentSchema = $this->connection->fetchOne(
            'SELECT current_schema()'
        );

        if ($currentSchema !== $schema) {
            throw new \RuntimeException(
                sprintf(
                    'No fue posible activar el schema "%s". Schema actual: "%s".',
                    $schema,
                    $currentSchema
                )
            );
        }

        /*
         * Dejamos disponible el tenant para cualquier otro servicio
         * de la aplicación.
         */
        $this->tenantContext->setSchema($schema);

        /*
         * También lo dejamos disponible dentro del Request.
         */
        $request->attributes->set('_tenant_schema', $schema);
    }
}