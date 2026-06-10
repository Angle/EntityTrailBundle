<?php

declare(strict_types=1);

namespace Angle\TrailBundle\Entity;

use Angle\TrailBundle\Repository\TrailLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrailLogRepository::class)]
#[ORM\Table(name: 'trail_logs')]
#[ORM\Index(name: 'idx_trail_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'idx_trail_created', columns: ['created_at'])]
#[ORM\Index(name: 'idx_trail_user', columns: ['user_id'])]
#[ORM\HasLifecycleCallbacks]
class TrailLog
{
    public const ACTION_CREATE = 'create';
    public const ACTION_UPDATE = 'update';
    public const ACTION_DELETE = 'delete';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER, options: ['unsigned' => true])]
    private ?int $id = null;

    #[ORM\Column(length: 32, unique: true)]
    private string $code;

    #[ORM\Column(name: 'entity_type', length: 255)]
    private string $entityType;

    #[ORM\Column(name: 'entity_id', type: Types::INTEGER, options: ['unsigned' => true])]
    private int $entityId;

    #[ORM\Column(name: 'entity_code', length: 32, nullable: true)]
    private ?string $entityCode = null;

    #[ORM\Column(length: 16)]
    private string $action;

    /**
     * @var array<string, array{old: mixed, new: mixed}>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $changes = [];

    #[ORM\Column(name: 'user_id', type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $userId = null;

    #[ORM\Column(name: 'user_label', length: 255, nullable: true)]
    private ?string $userLabel = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if (!isset($this->code)) {
            $this->code = bin2hex(random_bytes(16));
        }
        if (!isset($this->createdAt)) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;

        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;

        return $this;
    }

    public function getEntityCode(): ?string
    {
        return $this->entityCode;
    }

    public function setEntityCode(?string $entityCode): self
    {
        $this->entityCode = $entityCode;

        return $this;
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): self
    {
        $this->action = $action;

        return $this;
    }

    /**
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public function getChanges(): array
    {
        return $this->changes;
    }

    /**
     * @param array<string, array{old: mixed, new: mixed}> $changes
     */
    public function setChanges(array $changes): self
    {
        $this->changes = $changes;

        return $this;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getUserLabel(): ?string
    {
        return $this->userLabel;
    }

    public function setUserLabel(?string $userLabel): self
    {
        $this->userLabel = $userLabel;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Short, human-friendly class name of the audited entity (last namespace segment).
     */
    public function getEntityShortName(): string
    {
        $pos = strrpos($this->entityType, '\\');

        return $pos === false ? $this->entityType : substr($this->entityType, $pos + 1);
    }
}
