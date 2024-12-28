<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Application;
use App\Core\Request;
use App\Controller\AuthController;
use App\Controller\UserController;
use App\Controller\ActivationController;
use App\Controller\ActivityController;
use App\Controller\PasswordResetController;
use App\Controller\ProfileController;
use App\Middleware\JWTMiddleware;
use Dotenv\Dotenv;

// Desactivar mostrar errores
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Cargar variables de entorno
$dotenv = Dotenv::createImmutable(dirname(__DIR__));
$dotenv->load();

function customLog($message, $type = 'INFO') {
    $logFile = __DIR__ . '/../local/logs/debug.log';
    $logDir = dirname($logFile);

    // Crear directorio de logs si no existe
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Formatear el mensaje
    $timestamp = date('Y-m-d H:i:s');
    $formattedMessage = "[{$timestamp}] [{$type}] {$message}" . PHP_EOL;

    // Escribir al archivo
    file_put_contents($logFile, $formattedMessage, FILE_APPEND);
}

$headers = getallheaders();
// customLog("Request Headers: " . json_encode($headers), 'DEBUG');

// Registrar método y URI
// customLog("Request Method: " . $_SERVER['REQUEST_METHOD'], 'DEBUG');
// customLog("Request URI: " . $_SERVER['REQUEST_URI'], 'DEBUG');

// Registrar el cuerpo de la petición
$rawInput = file_get_contents('php://input');
customLog("Raw Input: " . $rawInput, 'DEBUG');

// Headers de seguridad y CORS
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
// header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // http_response_code(200);
    // customLog("OPTIONS request handled", 'DEBUG');
    exit();
}

// Obtener la ruta de la URL
// $request_uri = $_SERVER['REQUEST_URI'];
// $path = trim(parse_url($request_uri, PHP_URL_PATH), '/');
// $path = str_replace('api/', '', $path);

// // customLog("Processed Path: " . $path, 'DEBUG');

// // Obtener el cuerpo de la petición
// $input = file_get_contents('php://input');
// $data = json_decode($input, true);

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
    return (new UserController($app->em))->listUsers($request);
});

// Rutas de usuarios (protegidas)
$app->router->post('/api/users', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new UserController($app->em))->create($request);
});

$app->router->put('/api/users/{id}', function(Request $request, $id) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new UserController($app->em))->update($request, (int)$id);
});

$app->router->delete('/api/users/{id}', function(Request $request, $id) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new UserController($app->em))->delete($request, (int)$id);
});

$app->router->post('/api/activate', function(Request $request) use ($app) {
    return (new ActivationController($app->em))->activate($request);
});

$app->router->post('/api/activation/resend', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new ActivationController($app->em))->resendActivation($request);
});

$app->router->get('/api/profile', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new ProfileController($app->em))->getProfile($request);
});

$app->router->post('/api/profile/change-password', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new ProfileController($app->em))->changePassword($request);
});

$app->router->post('/api/login', [AuthController::class, 'login']);
$app->router->post('/api/refresh', [AuthController::class, 'refresh']);
$app->router->post('/api/logout', [AuthController::class, 'logout']);

$app->router->post('/api/password/reset-request', function(Request $request) use ($app) {
    return (new PasswordResetController($app->em))->requestReset($request);
});

$app->router->post('/api/password/reset', function(Request $request) use ($app) {
    return (new PasswordResetController($app->em))->resetPassword($request);
});

// Ruta para ver el historial de actividad
$app->router->get('/api/activity', function(Request $request) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new ActivityController())->getUserActivity($request);
});

// Ruta para ver los detalles de un usuario específico (incluye actividad reciente)
$app->router->get('/api/users/{id}/details', function(Request $request, $id) use ($app) {
    $middleware = new JWTMiddleware();
    if (!$middleware->handle($request)) {
        return $app->response->json([
            'status' => 'error',
            'message' => 'Unauthorized'
        ], 401);
    }
    return (new ActivityController())->getUserDetails($request, (int)$id);
});

$app->run();