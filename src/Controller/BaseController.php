<?php
namespace App\Controller;

use App\Core\Request;
use App\Core\Response;

abstract class BaseController
{
    protected Request $request;
    protected Response $response;

    public function __construct()
    {
        $this->request = new Request();
        $this->response = new Response();
    }

    protected function json($data, int $status = 200): string
    {
        return $this->response->json($data, $status);
    }
}