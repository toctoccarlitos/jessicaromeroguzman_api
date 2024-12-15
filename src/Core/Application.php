<?php
namespace App\Core;

use Doctrine\ORM\EntityManager;

class Application
{
    public static string $ROOT_DIR;
    public static Application $app;
    public Router $router;
    public Request $request;
    public Response $response;
    public EntityManager $em;

    public function __construct(string $rootPath)
    {
        self::$ROOT_DIR = $rootPath;
        self::$app = $this;
        $this->request = new Request();
        $this->response = new Response();
        $this->router = new Router($this->request, $this->response);

        // Debug temporal
        // var_dump([
        //     'env_loaded' => $_ENV,
        //     'db_config' => require $rootPath . '/config/database.php'
        // ]);

        $this->em = require $rootPath . '/config/doctrine.php';
    }

    public function run()
    {
        try {
            echo $this->router->resolve();
        } catch (\Exception $e) {
            $this->response->setStatusCode(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }
}