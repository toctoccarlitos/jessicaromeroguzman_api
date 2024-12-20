<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_logs')]
#[ORM\HasLifecycleCallbacks]
class ActivityLog
{
    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGOUT = 'logout';
    public const TYPE_PASSWORD_CHANGE = 'password_change';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_PASSWORD_RESET_REQUEST = 'password_reset_request';
    public const TYPE_ACCOUNT_ACTIVATION = 'account_activation';
    public const TYPE_PROFILE_VIEW = 'profile_view';
    public const TYPE_PROFILE_UPDATE = 'profile_update';
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(User $user, string $type, string $description)
    {
        $this->user = $user;
        $this->type = $type;
        $this->description = $description;
        $this->ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}