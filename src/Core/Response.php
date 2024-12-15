<?php
namespace App\Core;

class Response
{
    public function setStatusCode(int $code): void
    {
        http_response_code($code);
    }

    public function json($data, int $statusCode = 200): string
    {
        $this->setStatusCode($statusCode);
        header('Content-Type: application/json');

        if (is_array($data)) {
            array_walk_recursive($data, function (&$value) {
                if (is_string($value)) {
                    $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
                }
            });
        }

        return json_encode($data);
    }
}