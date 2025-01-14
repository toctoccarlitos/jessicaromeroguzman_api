<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activity_logs')]
#[ORM\HasLifecycleCallbacks]
class ActivityLog
{
    // Constantes existentes
    public const TYPE_LOGIN = 'login';
    public const TYPE_LOGOUT = 'logout';
    public const TYPE_PASSWORD_CHANGE = 'password_change';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_PASSWORD_RESET_REQUEST = 'password_reset_request';
    public const TYPE_ACCOUNT_ACTIVATION = 'account_activation';
    public const TYPE_PROFILE_VIEW = 'profile_view';
    public const TYPE_PROFILE_UPDATE = 'profile_update';
    public const TYPE_EMAIL_VERIFICATION = 'email_verification';

    // Nuevas constantes para eventos de seguridad
    public const TYPE_SECURITY_SPAM = 'security_spam';
    public const TYPE_SECURITY_RATE_LIMIT = 'security_rate_limit';
    public const TYPE_SECURITY_HONEYPOT = 'security_honeypot';
    public const TYPE_SECURITY_CSRF = 'security_csrf';
    public const TYPE_SECURITY_RECAPTCHA = 'security_recaptcha';
    public const TYPE_SECURITY_INVALID_INPUT = 'security_invalid_input';
    public const TYPE_SECURITY_SUSPICIOUS = 'security_suspicious';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    public function __construct(string $type = '', string $description = '', ?string $ipAddress = null)
    {
        $this->type = $type;
        $this->description = $description;
        $this->ipAddress = $ipAddress;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }
}