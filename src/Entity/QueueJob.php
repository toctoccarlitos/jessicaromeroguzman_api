<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'queue_jobs')]
#[ORM\Index(columns: ['queue'])]
class QueueJob
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint', options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    private string $queue;

    #[ORM\Column(type: 'text')]
    private string $payload;

    #[ORM\Column(type: 'integer', options: ['unsigned' => true])]
    private int $attempts = 0;

    #[ORM\Column(name: 'created_at', type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(name: 'reserved_at', type: 'datetime', nullable: true)]
    private ?\DateTime $reservedAt = null;

    #[ORM\Column(name: 'available_at', type: 'datetime')]
    private \DateTime $availableAt;

    #[ORM\Column(name: 'last_error', type: 'text', nullable: true)]
    private ?string $lastError = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->availableAt = new \DateTime();
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

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): self
    {
        $this->attempts = $attempts;
        return $this;
    }

    public function getAvailableAt(): \DateTime
    {
        return $this->availableAt;
    }

    public function setAvailableAt(\DateTime $availableAt): self
    {
        $this->availableAt = $availableAt;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getReservedAt(): ?\DateTime
    {
        return $this->reservedAt;
    }

    public function setReservedAt(?\DateTime $reservedAt): self
    {
        $this->reservedAt = $reservedAt;
        return $this;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): self
    {
        $this->lastError = $lastError;
        return $this;
    }
}