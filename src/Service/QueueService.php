<?php
namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

class QueueService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 300; // 5 minutos
    private const JOB_TIMEOUT = 300; // 5 minutos

    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function push(string $queue, array $job, int $delay = 0): bool
    {
        try {
            logger()->debug('Pushing job to queue', [
                'queue' => $queue,
                'delay' => $delay,
                'job_type' => $job['type'] ?? 'unknown'
            ]);

            $conn = $this->em->getConnection();
            $job['attempts'] = 0;
            $job['created_at'] = time();

            $availableAt = date('Y-m-d H:i:s', time() + $delay);

            $conn->executeStatement(
                'INSERT INTO queue_jobs (queue, payload, available_at, created_at) VALUES (?, ?, ?, NOW())',
                [$queue, json_encode($job), $availableAt]
            );

            logger()->info('Job pushed successfully', [
                'queue' => $queue,
                'available_at' => $availableAt
            ]);
            return true;
        } catch (\Exception $e) {
            logger()->error('Queue push failed', [
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function process(string $queue, callable $callback): void
    {
        logger()->info('Starting queue processor', ['queue' => $queue]);

        while (true) {
            try {
                $conn = $this->em->getConnection();

                logger()->debug('Looking for jobs to process', ['queue' => $queue]);
                $conn->beginTransaction();

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

                logger()->debug('Job found', [
                    'job_id' => $job['id'],
                    'attempts' => $job['attempts']
                ]);

                $conn->executeStatement(
                    'UPDATE queue_jobs SET reserved_at = NOW() WHERE id = ?',
                    [$job['id']]
                );

                $conn->commit();

                $jobData = json_decode($job['payload'], true);
                $jobData['attempts']++;

                logger()->info('Processing job', [
                    'job_id' => $job['id'],
                    'attempt' => $jobData['attempts'],
                    'job_type' => $jobData['type'] ?? 'unknown'
                ]);

                $callback($jobData);

                $conn->executeStatement(
                    'DELETE FROM queue_jobs WHERE id = ?',
                    [$job['id']]
                );

                logger()->info('Job completed successfully', [
                    'job_id' => $job['id']
                ]);

            } catch (\Exception $e) {
                if (isset($conn) && $conn->isTransactionActive()) {
                    $conn->rollBack();
                }

                if (isset($job)) {
                    logger()->error('Job processing failed', [
                        'job_id' => $job['id'],
                        'error' => $e->getMessage()
                    ]);
                    $this->handleFailedJob($queue, $job, $jobData ?? [], $e);
                } else {
                    logger()->error('Queue processing error', [
                        'queue' => $queue,
                        'error' => $e->getMessage()
                    ]);
                }

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
                $delay = self::RETRY_DELAY * pow(2, $attempts - 1);
                $availableAt = date('Y-m-d H:i:s', time() + $delay);

                logger()->warning('Scheduling job retry', [
                    'job_id' => $job['id'],
                    'attempt' => $attempts,
                    'delay' => $delay,
                    'available_at' => $availableAt
                ]);

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
                logger()->error('Job failed permanently', [
                    'job_id' => $job['id'],
                    'queue' => $queue,
                    'total_attempts' => $attempts,
                    'final_error' => $e->getMessage()
                ]);

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
            logger()->error('Failed to handle failed job', [
                'job_id' => $job['id'],
                'queue' => $queue,
                'error' => $e->getMessage()
            ]);
        }
    }
}