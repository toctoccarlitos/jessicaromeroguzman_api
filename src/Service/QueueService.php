<?php
namespace App\Service;

use App\Service\Logger\AppLogger;
use Doctrine\ORM\EntityManagerInterface;

class QueueService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 300; // 5 minutos
    private const JOB_TIMEOUT = 300; // 5 minutos

    private EntityManagerInterface $em;
    private AppLogger $logger;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
        $this->logger = new AppLogger();
    }

    public function push(string $queue, array $job, int $delay = 0): bool
    {
        try {
            $conn = $this->em->getConnection();
            $job['attempts'] = 0;
            $job['created_at'] = time();
            
            $availableAt = date('Y-m-d H:i:s', time() + $delay);
            
            $conn->executeStatement(
                'INSERT INTO queue_jobs (queue, payload, available_at, created_at) VALUES (?, ?, ?, NOW())',
                [$queue, json_encode($job), $availableAt]
            );

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Queue push failed', ['queue' => $queue], $e);
            return false;
        }
    }

    public function process(string $queue, callable $callback): void
    {
        while (true) {
            try {
                $conn = $this->em->getConnection();
                
                // Comenzar transacción
                $conn->beginTransaction();
                
                // Obtener y reservar un trabajo
                $job = $conn->executeQuery(
                    'SELECT * FROM queue_jobs 
                     WHERE queue = ? 
                     AND available_at <= NOW() 
                     AND reserved_at IS NULL 
                     AND attempts < ? 
                     LIMIT 1 FOR UPDATE',
                    [$queue, self::MAX_RETRIES]
                )->fetchAssociative();

                if (!$job) {
                    $conn->rollBack();
                    sleep(1);
                    continue;
                }

                // Marcar como reservado
                $conn->executeStatement(
                    'UPDATE queue_jobs SET reserved_at = NOW() WHERE id = ?',
                    [$job['id']]
                );

                $conn->commit();

                // Procesar el trabajo
                $jobData = json_decode($job['payload'], true);
                $jobData['attempts']++;

                $callback($jobData);

                // Eliminar trabajo completado
                $conn->executeStatement(
                    'DELETE FROM queue_jobs WHERE id = ?',
                    [$job['id']]
                );

            } catch (\Exception $e) {
                if (isset($conn) && $conn->isTransactionActive()) {
                    $conn->rollBack();
                }

                if (isset($job)) {
                    $this->handleFailedJob($queue, $job, $jobData ?? [], $e);
                }

                $this->logger->error('Job processing failed', [
                    'queue' => $queue,
                    'job_id' => $job['id'] ?? null
                ], $e);

                sleep(1);
            }
        }
    }

    private function handleFailedJob(string $queue, array $job, array $jobData, \Exception $e): void
    {
        try {
            $conn = $this->em->getConnection();
            $attempts = ($jobData['attempts'] ?? 0) + 1;

            if ($attempts < self::MAX_RETRIES) {
                // Reintentar con delay exponencial
                $delay = self::RETRY_DELAY * pow(2, $attempts - 1);
                $availableAt = date('Y-m-d H:i:s', time() + $delay);

                $conn->executeStatement(
                    'UPDATE queue_jobs 
                     SET attempts = ?, 
                         available_at = ?,
                         reserved_at = NULL,
                         last_error = ?
                     WHERE id = ?',
                    [
                        $attempts,
                        $availableAt,
                        $e->getMessage(),
                        $job['id']
                    ]
                );
            } else {
                // Mover a la tabla de trabajos fallidos
                $conn->executeStatement(
                    'INSERT INTO failed_jobs 
                     (queue, payload, exception, failed_at) 
                     VALUES (?, ?, ?, NOW())',
                    [
                        $queue,
                        json_encode($jobData),
                        json_encode([
                            'message' => $e->getMessage(),
                            'trace' => $e->getTraceAsString()
                        ])
                    ]
                );

                // Eliminar de la cola principal
                $conn->executeStatement(
                    'DELETE FROM queue_jobs WHERE id = ?',
                    [$job['id']]
                );
            }
        } catch (\Exception $e) {
            $this->logger->error('Failed to handle failed job', [
                'queue' => $queue,
                'job_id' => $job['id']
            ], $e);
        }
    }
}