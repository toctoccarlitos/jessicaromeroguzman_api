<?php
namespace App\Controller;

use App\Core\Response;
use App\Service\TokenService;

abstract class BaseController
{
    protected TokenService $tokenService;
    protected Response $response;

    public function __construct()
    {
        $this->tokenService = new TokenService(app()->em);
        $this->response = new Response();
    }

    protected function extractTokenFromHeader(): ?string
    {
        return $this->tokenService->extractTokenFromHeader();
    }

    protected function json($data, int $status = 200): string
    {
        return $this->response->json($data, $status);
    }
}