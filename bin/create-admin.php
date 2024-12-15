<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Entity\User;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

$em = require __DIR__ . '/../config/doctrine.php';

try {
    $admin = new User();
    $admin->setEmail('info@jessicaromeroguzman.com')
          ->setPassword('85>o,fnA@M-F3D£C')  // Cambia esto por una contraseña segura
          ->setRoles([User::ROLE_ADMIN])
          ->setStatus(User::STATUS_ACTIVE);

    $em->persist($admin);
    $em->flush();

    echo "Usuario admin creado exitosamente!\n";
} catch (\Exception $e) {
    echo "Error creando usuario admin: " . $e->getMessage() . "\n";
}