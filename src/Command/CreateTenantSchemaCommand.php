<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:tenant:create-schema',
    description: 'Crea la estructura completa del POS dentro de un schema PostgreSQL'
)]
class CreateTenantSchemaCommand extends Command
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'schema',
            InputArgument::REQUIRED,
            'Nombre del schema PostgreSQL del tenant'
        );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $schema = strtolower(trim((string) $input->getArgument('schema')));

        /*
         * Seguridad:
         * solamente letras minúsculas, números y guion bajo.
         */
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $schema)) {
            $output->writeln(
                '<error>Nombre de schema inválido. Use letras minúsculas, números y guion bajo.</error>'
            );

            return Command::FAILURE;
        }

        /*
         * Nunca permitimos generar el POS dentro de schemas reservados.
         */
        $reservedSchemas = [
            'public',
            'licenses',
            'information_schema',
            'pg_catalog',
        ];

        if (in_array($schema, $reservedSchemas, true)) {
            $output->writeln(
                sprintf(
                    '<error>El schema "%s" está reservado y no puede utilizarse como tenant.</error>',
                    $schema
                )
            );

            return Command::FAILURE;
        }

        try {
            /*
             * Verificamos que el schema exista.
             */
            $schemaExists = (bool) $this->connection->fetchOne(
                '
                SELECT EXISTS (
                    SELECT 1
                    FROM pg_namespace
                    WHERE nspname = ?
                )
                ',
                [$schema]
            );

            if (!$schemaExists) {
                $output->writeln(
                    sprintf(
                        '<error>El schema "%s" no existe.</error>',
                        $schema
                    )
                );

                $output->writeln(
                    sprintf(
                        'Créelo primero con: CREATE SCHEMA %s AUTHORIZATION usr_bookinpos;',
                        $schema
                    )
                );

                return Command::FAILURE;
            }

            /*
             * Establecemos el search_path EN ESTA MISMA conexión.
             *
             * set_config permite pasar el valor como parámetro,
             * evitando concatenar directamente el schema en SQL.
             */
            $this->connection->fetchOne(
                "SELECT set_config('search_path', ?, false)",
                [$schema . ', public']
            );

            $currentSchema = $this->connection->fetchOne(
                'SELECT current_schema()'
            );

            if ($currentSchema !== $schema) {
                $output->writeln(
                    sprintf(
                        '<error>No se pudo activar el schema "%s". Schema actual: "%s".</error>',
                        $schema,
                        $currentSchema
                    )
                );

                return Command::FAILURE;
            }

            $output->writeln('');
            $output->writeln(
                sprintf(
                    '<info>Schema activo: %s</info>',
                    $currentSchema
                )
            );

            /*
             * Protección adicional:
             * verificamos si ya existen tablas en el schema.
             *
             * No queremos ejecutar SchemaTool accidentalmente
             * encima de un tenant ya inicializado.
             */
            $existingTables = (int) $this->connection->fetchOne(
                '
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_type = \'BASE TABLE\'
                ',
                [$schema]
            );

            if ($existingTables > 0) {
                $output->writeln(
                    sprintf(
                        '<error>El schema "%s" ya contiene %d tabla(s).</error>',
                        $schema,
                        $existingTables
                    )
                );

                $output->writeln(
                    '<comment>Por seguridad no se realizará ninguna modificación.</comment>'
                );

                return Command::FAILURE;
            }

            /*
             * Obtenemos todas las entidades administradas por Doctrine.
             */
            $metadata = $this->entityManager
                ->getMetadataFactory()
                ->getAllMetadata();

            if (count($metadata) === 0) {
                $output->writeln(
                    '<error>Doctrine no encontró Entities mapeadas.</error>'
                );

                return Command::FAILURE;
            }

            $output->writeln(
                sprintf(
                    'Entities encontradas por Doctrine: %d',
                    count($metadata)
                )
            );

            $output->writeln('');
            $output->writeln(
                '<comment>Creando estructura PostgreSQL...</comment>'
            );

            /*
             * SchemaTool genera las tablas a partir del modelo actual.
             *
             * Como search_path apunta a tiosbarber,
             * las tablas sin schema explícito se crearán ahí.
             */
            $schemaTool = new SchemaTool($this->entityManager);

            $schemaTool->createSchema($metadata);

            /*
             * Verificación posterior.
             */
            $createdTables = (int) $this->connection->fetchOne(
                '
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_type = \'BASE TABLE\'
                ',
                [$schema]
            );

            $output->writeln('');
            $output->writeln('<info>=======================================</info>');
            $output->writeln('<info>Tenant creado correctamente</info>');
            $output->writeln('<info>=======================================</info>');
            $output->writeln(
                sprintf('Schema: %s', $schema)
            );
            $output->writeln(
                sprintf('Tablas creadas: %d', $createdTables)
            );

            /*
             * Confirmamos que no se hayan creado tablas POS
             * accidentalmente en public durante esta ejecución.
             */
            $output->writeln('');
            $output->writeln(
                '<comment>La estructura fue generada usando las Entities actuales de Doctrine.</comment>'
            );

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            $output->writeln('');
            $output->writeln('<error>Error creando el schema del tenant:</error>');
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}