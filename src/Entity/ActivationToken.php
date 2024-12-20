<?php
namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activation_tokens')]
#[ORM\HasLifecycleCallbacks]
class ActivationToken
{
   #[ORM\Id]
   #[ORM\Column(type: 'integer')]
   #[ORM\GeneratedValue]
   private ?int $id = null;

   #[ORM\OneToOne(targetEntity: User::class)]
   #[ORM\JoinColumn(nullable: false)]
   private User $user;

   #[ORM\Column(type: 'string', length: 64, unique: true)]
   private string $token;

   #[ORM\Column(type: 'datetime')]
   private \DateTime $expiresAt;

   #[ORM\Column(type: 'boolean')]
   private bool $used = false;

   #[ORM\Column(type: 'datetime')]
   private \DateTime $createdAt;

   public function __construct(User $user)
   {
       $this->user = $user;
       $this->token = bin2hex(random_bytes(32));
       $this->expiresAt = new \DateTime('+48 hours');
       $this->createdAt = new \DateTime();
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

   public function isValid(): bool
   {
       return !$this->used && $this->expiresAt > new \DateTime();
   }

   public function setUsed(bool $used): self
   {
       $this->used = $used;
       return $this;
   }

   public function isUsed(): bool
   {
       return $this->used;
   }

   public function getExpiresAt(): \DateTime
   {
       return $this->expiresAt;
   }

   public function getCreatedAt(): \DateTime
   {
       return $this->createdAt;
   }

   #[ORM\PrePersist]
   public function setCreatedAtValue(): void
   {
       $this->createdAt = new \DateTime();
   }
}