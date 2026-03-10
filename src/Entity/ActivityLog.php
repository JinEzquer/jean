<?php

namespace App\Entity;

use App\Repository\ActivityLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ActivityLogRepository::class)]
class ActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private $user;

    #[ORM\Column(type: 'string', length: 255)]
    private $action;

    #[ORM\Column(type: 'json', nullable: true)]
    private $targetData = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private $createdAt;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $username;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private $userRole;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        if ($user) {
            $this->username = $user->getEmail();
            // Get the primary role (Admin > Staff > User)
            $roles = $user->getRoles();
            if (in_array('ROLE_ADMIN', $roles)) {
                $this->userRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_STAFF', $roles)) {
                $this->userRole = 'ROLE_STAFF';
            } else {
                $this->userRole = 'ROLE_USER';
            }
        }
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getTargetData(): ?array
    {
        return $this->targetData;
    }

    public function setTargetData(?array $targetData): self
    {
        $this->targetData = $targetData;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getUserRole(): ?string
    {
        return $this->userRole;
    }

    public function getFormattedTargetData(): string
    {
        $targetData = $this->getTargetData();
        if (isset($targetData['formatted'])) {
            return $targetData['formatted'];
        }
        return json_encode($targetData);
    }
}
