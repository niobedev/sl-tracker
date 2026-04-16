<?php

namespace App\Entity;

use App\Repository\TrackedAvatarRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TrackedAvatarRepository::class)]
#[ORM\Table(name: 'tracked_avatar')]
#[ORM\Index(columns: ['tracking_enabled'], name: 'idx_tracked_avatar_enabled')]
#[ORM\HasLifecycleCallbacks]
class TrackedAvatar
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'avatar_key', length: 36, unique: true)]
    private string $avatarKey;

    #[ORM\Column(name: 'tracking_enabled', type: 'boolean')]
    private bool $trackingEnabled = true;

    #[ORM\ManyToOne(targetEntity: NotificationChannel::class)]
    #[ORM\JoinColumn(name: 'notification_channel_id', referencedColumnName: 'id', onDelete: 'SET NULL')]
    private ?NotificationChannel $notificationChannel = null;

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

    public function getAvatarKey(): string
    {
        return $this->avatarKey;
    }

    public function setAvatarKey(string $avatarKey): static
    {
        $this->avatarKey = strtolower($avatarKey);
        return $this;
    }

    public function isTrackingEnabled(): bool
    {
        return $this->trackingEnabled;
    }

    public function setTrackingEnabled(bool $trackingEnabled): static
    {
        $this->trackingEnabled = $trackingEnabled;
        return $this;
    }

    public function getNotificationChannel(): ?NotificationChannel
    {
        return $this->notificationChannel;
    }

    public function setNotificationChannel(?NotificationChannel $notificationChannel): static
    {
        $this->notificationChannel = $notificationChannel;
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
