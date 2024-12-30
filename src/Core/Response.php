<?php
namespace App\Core;

class Response
{
    private $statusCode = 200;
    private $headers = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function json($data, int $statusCode = 200): string
    {
        $this->statusCode = $statusCode;
        $this->addHeader('Content-Type', 'application/json');

        // Buffer the output
        ob_start();

        // Set status code
        http_response_code($this->statusCode);

        // Set headers
        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

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