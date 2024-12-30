<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\HasLifecycleCallbacks]
class User
{
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_BLOCKED = 'BLOCKED';

    #[ORM\Id]
    #[ORM\Column(type: 'integer')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updatedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $emailVerifiedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastLoginAt = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: 'boolean')]
    private bool $hasSetPassword = false;

    #[ORM\Column(type: 'string', length: 100)]
    private string $firstName;

    #[ORM\Column(type: 'string', length: 100)]
    private string $lastName;

    #[ORM\Column(type: 'date')]
    private \DateTime $birthDate;

    #[ORM\Column(type: 'string', length: 20)]
    private string $mobilePhone;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->status = self::STATUS_PENDING;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAt()
    {
        $this->updatedAt = new \DateTime();
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

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = password_hash($password, PASSWORD_ARGON2ID);
        return $this;
    }

    public function verifyPassword(string $password): bool
    {
        return password_verify($password, $this->password);
    }

    public function getRoles(): array
    {
        return array_unique($this->roles);
    }

    public function setRoles(array $roles): self
    {
        // Limpiamos el array de roles existente
        $this->roles = [];

        // Agregamos los nuevos roles, asegurándonos que sean únicos
        foreach ($roles as $role) {
            if (!in_array($role, $this->roles)) {
                $this->roles[] = $role;
            }
        }

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [self::STATUS_PENDING, self::STATUS_ACTIVE, self::STATUS_BLOCKED])) {
            throw new \InvalidArgumentException('Invalid status');
        }
        $this->status = $status;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updatedAt;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function getEmailVerifiedAt(): ?\DateTime
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(\DateTime $date): self
    {
        $this->emailVerifiedAt = $date;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(\DateTime $date): self
    {
        $this->lastLoginAt = $date;
        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(string $ip): self
    {
        $this->lastLoginIp = $ip;
        return $this;
    }

    public function hasSetPassword(): bool
    {
        return $this->hasSetPassword;
    }

    public function setHasSetPassword(bool $value): self
    {
        $this->hasSetPassword = $value;
        return $this;
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getBirthDate(): \DateTime
    {
        return $this->birthDate;
    }

    public function setBirthDate(\DateTime $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getMobilePhone(): string
    {
        return $this->mobilePhone;
    }

    public function setMobilePhone(string $mobilePhone): self
    {
        $this->mobilePhone = $mobilePhone;
        return $this;
    }
}