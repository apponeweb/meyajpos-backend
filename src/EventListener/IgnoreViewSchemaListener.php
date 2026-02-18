<?php

namespace App\EventListener;

// Asegúrate de importar la clase correcta
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;

class IgnoreViewSchemaListener
{
    /**
     * @param GenerateSchemaEventArgs $args
     */
    public function postGenerateSchema(GenerateSchemaEventArgs $args): void
    {
        $schema = $args->getSchema();

        // El método correcto es hasTable y dropTable sobre el esquema completo
        if ($schema->hasTable('vw_daily_report')) {
            $schema->dropTable('vw_daily_report');
        }
    }
}
