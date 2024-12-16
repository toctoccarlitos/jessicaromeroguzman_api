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
        $data = [];

        if ($this->isGet()) {
            foreach ($_GET as $key => $value) {
                $data[$key] = filter_input(INPUT_GET, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        if ($this->isPost()) {
            $input = json_decode(file_get_contents('php://input'), true);
            if ($input) {
                return $input;
            }

            foreach ($_POST as $key => $value) {
                $data[$key] = filter_input(INPUT_POST, $key, FILTER_SANITIZE_SPECIAL_CHARS);
            }
        }

        return $data;
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
}