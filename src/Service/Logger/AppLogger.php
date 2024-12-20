<?php
namespace App\Service\Logger;

class AppLogger
{
    private string $logPath;

    public function __construct()
    {
        // Definir la ruta base del proyecto
        $projectRoot = dirname(dirname(dirname(__DIR__)));

        // Si LOG_PATH está definido en .env, usarlo; si no, usar la ruta por defecto
        $this->logPath = $_ENV['LOG_PATH'] ?? $projectRoot . '/logs';

        // Asegurarse de que el directorio existe y es escribible
        if (!is_dir($this->logPath)) {
            if (!mkdir($this->logPath, 0777, true)) {
                throw new \RuntimeException(
                    "No se pudo crear el directorio de logs: " . $this->logPath
                );
            }
        }

        if (!is_writable($this->logPath)) {
            throw new \RuntimeException(
                "El directorio de logs no tiene permisos de escritura: " . $this->logPath
            );
        }
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('INFO', $message, $context);
    }

    public function error(string $message, array $context = [], ?\Throwable $exception = null): void
    {
        if ($exception) {
            $context['exception'] = [
                'message' => $exception->getMessage(),
                'code' => $exception->getCode(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString()
            ];
        }
        $this->log('ERROR', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        $date = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '';
        $logMessage = sprintf(
            "[%s] [%s] %s%s" . PHP_EOL,
            $date,
            $level,
            $message,
            $contextString
        );

        $filename = sprintf(
            '%s/app_%s.log',
            $this->logPath,
            date('Y-m-d')
        );

        if (file_put_contents($filename, $logMessage, FILE_APPEND | LOCK_EX) === false) {
            error_log("Error writing to log file: " . $filename);
        }
    }
}