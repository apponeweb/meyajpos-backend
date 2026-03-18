<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();
$entityManager = $container->get('doctrine.orm.entity_manager');
$repository = $entityManager->getRepository(\App\Entity\AppointmentStatusConfig::class);

try {
    $configs = $repository->findAll();
    echo "Found " . count($configs) . " configs\n";
    foreach ($configs as $c) {
        echo "Status: " . $c->getStatus() . " Color: " . $c->getColor() . "\n";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
