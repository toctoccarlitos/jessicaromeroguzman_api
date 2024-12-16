<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Core\Request;
use App\Controller\AuthController;
use App\Controller\UserController;
use App\Middleware\JWTMiddleware;
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

// Rutas públicas
$app->router->get('/api/status', function() use ($app) {
    return $app->response->json([
        'status' => 'OK',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
});

$app->router->post('/api/login', [AuthController::class, 'login']);

// Rutas protegidas
$app->router->get('/api/profile', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new UserController($app->em))->profile($request);
});

$app->router->get('/api/users', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new UserController($app->em))->list($request);
});

$app->router->post('/api/login', [AuthController::class, 'login']);
$app->router->post('/api/refresh', [AuthController::class, 'refresh']);
$app->router->post('/api/logout', [AuthController::class, 'logout']);

$app->run();