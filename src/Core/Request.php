<?php
namespace App\Core;

class Request
{
    private $user = null;

    public function getMethod(): string
    {
        return strtolower($_SERVER['REQUEST_METHOD']);
    }

    public function getUrl(): string
    {
        $path = $_SERVER['REQUEST_URI'] ?? '/';
        $position = strpos($path, '?');

        if ($position === false) {
            return $path;
        }

        return substr($path, 0, $position);
    }

    public function getBody(): array
    {
        if ($this->isGet()) {
            foreach ($_GET as $key => $value) {
                $data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
            return $data ?? [];
        }

        // Para POST, PUT, DELETE
        $contentType = $this->getContentType();
        $content = file_get_contents('php://input');

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($content, true) ?? [];
        } else {
            parse_str($content, $data);
            foreach ($_POST as $key => $value) {
                $data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        return $data;
    }

    private function getContentType(): string
    {
        return $_SERVER['CONTENT_TYPE'] ?? '';
    }

    public function isGet(): bool
    {
        return $this->getMethod() === 'get';
    }

    public function isPost(): bool
    {
        return $this->getMethod() === 'post';
    }

    public function setUser($user): void
    {
        $this->user = $user;
    }

    public function getUser()
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return $this->user ? $this->user->uid : null;
    }

    public function hasRole(string $role): bool
    {
        return $this->user && in_array($role, $this->user->roles);
    }

    public function getQuery(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }
}