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

    // Generar las queries SQL para crear el schema completo
    $sqlSchemaQueries = $tool->getCreateSchemaSql($classes);

    // Nombre del archivo de salida
    $outputFile = __DIR__ . '/schema.sql';

    // Iniciar el contenido del archivo SQL con comentarios útiles
    $sqlContent = "-- Schema generado automáticamente " . date('Y-m-d H:i:s') . "\n";
    $sqlContent .= "-- Este archivo contiene solo la estructura de la base de datos\n\n";
    
    // Agregar SET para manejar caracteres especiales
    $sqlContent .= "SET NAMES utf8mb4;\n";
    $sqlContent .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    // Agregar cada query al contenido
    foreach ($sqlSchemaQueries as $sql) {
        $sqlContent .= $sql . ";\n\n";
    }

    // Restaurar configuración
    $sqlContent .= "\nSET FOREIGN_KEY_CHECKS=1;\n";

    // Guardar el archivo
    if (file_put_contents($outputFile, $sqlContent)) {
        echo "Estructura de la base de datos exportada exitosamente a: $outputFile\n";
        echo "Total de queries generadas: " . count($sqlSchemaQueries) . "\n";
    } else {
        throw new Exception("No se pudo escribir en el archivo $outputFile");
    }

} catch (\Exception $e) {
    echo "Error durante la exportación: " . $e->getMessage() . "\n";
    exit(1);
}