<?php
namespace App\Core;

class Router
{
    private array $routes = [];

    public function __construct(
        private readonly Request $request,
        private readonly Response $response
    ) {}

    public function get(string $path, $callback): void
    {
        $this->addRoute('get', $path, $callback);
    }

    public function post(string $path, $callback): void
    {
        $this->addRoute('post', $path, $callback);
    }

    public function put(string $path, $callback): void
    {
        $this->addRoute('put', $path, $callback);
    }

    public function delete(string $path, $callback): void
    {
        $this->addRoute('delete', $path, $callback);
    }

    private function addRoute(string $method, string $path, $callback): void
    {
        // Convertir parámetros de ruta en expresiones regulares
        $pattern = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $path);
        $this->routes[strtolower($method)][$pattern] = $callback;
    }

    public function resolve()
{
    $method = $this->request->getMethod();
    $path = $this->request->getUrl();

    foreach ($this->routes[$method] ?? [] as $pattern => $callback) {
        // Convertir el patrón de ruta a expresión regular
        $regexPattern = str_replace('/', '\/', $pattern);
        $regexPattern = preg_replace('/\{(\w+)\}/', '(?P<$1>\d+)', $regexPattern);
        $regexPattern = "/^{$regexPattern}$/";

        if (preg_match($regexPattern, $path, $matches)) {
            // Extraer los parámetros
            $params = array_filter($matches, fn($key) => !is_numeric($key), ARRAY_FILTER_USE_KEY);

            if (is_array($callback)) {
                $controller = new $callback[0]();
                $callback[0] = $controller;
            }

            // Pasar la request y los parámetros al callback
            return call_user_func($callback, $this->request, ...array_values($params));
        }
    }

    // No se encontró la ruta
    $this->response->setStatusCode(404);
    return $this->response->json([
        'error' => 'Not found'
    ], 404);
}
}