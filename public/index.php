<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Controller\AuthController;
use Dotenv\Dotenv;

// Desactivar mostrar errores
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

// Headers de seguridad y CORS
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Iniciar aplicación
$app = new Application(dirname(__DIR__));

// Rutas de la API
$app->router->get('/api/status', function() use ($app) {
    return $app->response->json([
        'status' => 'OK',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

$app->router->post('/api/login', [AuthController::class, 'login']);

$app->run();