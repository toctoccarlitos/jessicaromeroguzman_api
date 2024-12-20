<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\ORM\Tools\SchemaTool;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Obtener EntityManager
$em = require __DIR__ . '/../config/doctrine.php';

try {
    $tool = new SchemaTool($em);
    $classes = $em->getMetadataFactory()->getAllMetadata();

    // Obtener las queries SQL que se ejecutarían
    $sqls = $tool->getUpdateSchemaSql($classes, true);

    if (empty($sqls)) {
        echo "La base de datos está actualizada. No se requieren cambios.\n";
        exit(0);
    }

    echo "Se detectaron " . count($sqls) . " cambios necesarios:\n";
    foreach ($sqls as $sql) {
        echo "- $sql\n";
    }

    echo "\n¿Deseas aplicar estos cambios? (y/n): ";
    $handle = fopen("php://stdin", "r");
    $line = fgets($handle);
    if (trim(strtolower($line)) != 'y') {
        echo "Operación cancelada.\n";
        exit(0);
    }

    // Aplicar los cambios de manera segura
    $tool->updateSchema($classes, true); // El true hace que sea seguro y no destructivo

    echo "Base de datos actualizada exitosamente!\n";
} catch (\Exception $e) {
    echo "Error durante la actualización: " . $e->getMessage() . "\n";
}