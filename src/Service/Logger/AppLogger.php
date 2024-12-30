<?php
namespace App\Service\Logger;

class AppLogger
{
    private string $logPath;
    private static ?self $instance = null;

    private function __construct()
    {
        // Definir la ruta base del proyecto
        $projectRoot = dirname(dirname(dirname(__DIR__)));

        // Ruta específica para los logs
        $this->logPath = $projectRoot . '/local/logs';

        // Crear el directorio con permisos adecuados si no existe
        if (!is_dir($this->logPath)) {
            $oldmask = umask(0);
            if (!mkdir($this->logPath, 0777, true)) {
                error_log("Error creating log directory: " . $this->logPath);
                throw new \RuntimeException("Could not create log directory");
            }
            umask($oldmask);

            // Asegurar permisos después de crear el directorio
            chmod($this->logPath, 0777);
        }
    }

    private function __clone() {}

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function debug($message, array $context = []): void
    {
        $this->writeLog('DEBUG', $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->writeLog('INFO', $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->writeLog('WARNING', $message, $context);
    }

    public function error($message, array $context = [], ?\Throwable $exception = null): void
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
        $this->writeLog('ERROR', $message, $context);
    }

    private function formatMessage($message): string
    {
        if (is_array($message) || is_object($message)) {
            return json_encode($message,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            ) ?: 'Error encoding message';
        }
        return (string)$message;
    }

    private function writeLog(string $level, $message, array $context = []): void
    {
        try {
            $logFile = $this->logPath . '/debug.log';

            // Si el archivo no existe, créalo con los permisos correctos
            if (!file_exists($logFile)) {
                touch($logFile);
                chmod($logFile, 0666);
            }

            $timestamp = date('Y-m-d H:i:s');
            $formattedMessage = $this->formatMessage($message);
            $contextString = !empty($context) ? ' ' . json_encode($context) : '';

            $logEntry = sprintf(
                "[%s] [%s] %s%s" . PHP_EOL,
                $timestamp,
                $level,
                $formattedMessage,
                $contextString
            );

            file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);

        } catch (\Exception $e) {
            error_log("Logger error: " . $e->getMessage());
        }
    }

    public function testLogPath(): array
    {
        return [
            'log_path' => $this->logPath,
            'exists' => file_exists($this->logPath),
            'is_dir' => is_dir($this->logPath),
            'is_writable' => is_writable($this->logPath),
            'permissions' => decoct(fileperms($this->logPath) & 0777)
        ];
    }
}