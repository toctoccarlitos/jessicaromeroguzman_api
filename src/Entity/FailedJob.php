<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'failed_jobs')]
class FailedJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $queue;

    #[ORM\Column(type: 'text')]
    private string $payload;

    #[ORM\Column(type: 'text')]
    private string $exception;

    #[ORM\Column(name: 'failed_at', type: 'datetime')]
    private \DateTime $failedAt;

    public function __construct()
    {
        $this->failedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQueue(): string
    {
        return $this->queue;
    }

    public function setQueue(string $queue): self
    {
        $this->queue = $queue;
        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): self
    {
        $this->payload = $payload;
        return $this;
    }

    public function getException(): string
    {
        return $this->exception;
    }

    public function setException(string $exception): self
    {
        $this->exception = $exception;
        return $this;
    }

    public function getFailedAt(): \DateTime
    {
        return $this->failedAt;
    }

    public function setFailedAt(\DateTime $failedAt): self
    {
        $this->failedAt = $failedAt;
        return $this;
    }
}