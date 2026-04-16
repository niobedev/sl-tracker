<?php

namespace App\Entity;

use App\Repository\NotificationChannelRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationChannelRepository::class)]
#[ORM\Table(name: 'notification_channel')]
#[ORM\Index(columns: ['type'], name: 'idx_channel_type')]
#[ORM\Index(columns: ['enabled'], name: 'idx_channel_enabled')]
#[ORM\HasLifecycleCallbacks]
class NotificationChannel
{
    public const TYPE_TELEGRAM = 'telegram';
    public const TYPE_DISCORD = 'discord';
    public const TYPE_MATRIX = 'matrix';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 50)]
    private string $type;

    #[ORM\Column(type: Types::JSON)]
    private array $config = [];

    #[ORM\Column(type: 'boolean')]
    private bool $enabled = true;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        if (!in_array($type, [self::TYPE_TELEGRAM, self::TYPE_DISCORD, self::TYPE_MATRIX])) {
            throw new \InvalidArgumentException("Invalid notification channel type: $type");
        }
        $this->type = $type;
        return $this;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function setConfig(array $config): static
    {
        $this->config = $config;
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): static
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
