<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 64, unique: true)]
    private string $token;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $expiresAt;

    #[ORM\Column(type: 'boolean')]
    private bool $isRevoked = false;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $createdByIp;

    public function __construct(User $user, int $expiresIn = 604800) // 7 días por defecto
    {
        $this->user = $user;
        $this->token = bin2hex(random_bytes(32));
        $this->expiresAt = new \DateTime("+{$expiresIn} seconds");
        $this->createdByIp = $_SERVER['REMOTE_ADDR'] ?? null;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTime();
    }

    public function isRevoked(): bool
    {
        return $this->isRevoked;
    }

    public function revoke(): void
    {
        $this->isRevoked = true;
    }

    public function isValid(): bool
    {
        return !$this->isRevoked && !$this->isExpired();
    }
}