<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'newsletters')]
#[ORM\HasLifecycleCallbacks]
class Newsletter
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status = 'active'; // active, unsubscribed

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $subscribedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $unsubscribedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->subscribedAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, ['active', 'unsubscribed'])) {
            throw new \InvalidArgumentException('Invalid status');
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getSubscribedAt(): \DateTime
    {
        return $this->subscribedAt;
    }

    public function setSubscribedAt(\DateTime $subscribedAt): self
    {
        $this->subscribedAt = $subscribedAt;
        return $this;
    }

    public function getUnsubscribedAt(): ?\DateTime
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?\DateTime $unsubscribedAt): self
    {
        $this->unsubscribedAt = $unsubscribedAt;
        return $this;
    }

    public function subscribe(): self
    {
        $this->status = 'active';
        $this->subscribedAt = new \DateTime();
        $this->unsubscribedAt = null;
        return $this;
    }

    public function unsubscribe(): self
    {
        $this->status = 'unsubscribed';
        $this->unsubscribedAt = new \DateTime();
        return $this;
    }
}