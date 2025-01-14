<?php
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;

$paths = [__DIR__ . '/../src/Entity'];
$isDevMode = $_ENV['APP_ENV'] === 'development';

// Configuración de Doctrine
$config = ORMSetup::createAttributeMetadataConfiguration(
    $paths,
    $isDevMode
);

// Configuración de la base de datos
$connection = DriverManager::getConnection(
    require __DIR__ . '/database.php',
    $config
);

// Crear EntityManager
$entityManager = EntityManager::create($connection, $config);

$securityService = new \App\Service\SecurityService(
    $entityManager,
    new \App\Service\CacheService($entityManager)
);

// Hacer disponible el servicio globalmente
$GLOBALS['securityService'] = $securityService;

return $entityManager;