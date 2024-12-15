<?php
require_once __DIR__ . '/../vendor/autoload.php';

use Doctrine\ORM\Tools\SchemaTool;
use Dotenv\Dotenv;

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Obtener EntityManager
$em = require __DIR__ . '/../config/doctrine.php';

// Crear schema
$tool = new SchemaTool($em);
$classes = $em->getMetadataFactory()->getAllMetadata();

try {
    $tool->createSchema($classes);
    echo "Base de datos creada exitosamente!\n";
} catch (\Exception $e) {
    echo "Error creando la base de datos: " . $e->getMessage() . "\n";
}