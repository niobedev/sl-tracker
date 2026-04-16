<?php

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table(name: 'event')]
#[ORM\Index(name: 'idx_event_ts', columns: ['event_ts'])]
#[ORM\Index(name: 'idx_avatar_key', columns: ['avatar_key'])]
#[ORM\Index(name: 'idx_action_ts', columns: ['action', 'event_ts'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'event_ts', type: 'datetime_immutable')]
    private \DateTimeImmutable $eventTs;

    #[ORM\Column(length: 10)]
    private string $action; // 'join' | 'quit'

    #[ORM\Column(name: 'avatar_key', length: 36)]
    private string $avatarKey;

    #[ORM\Column(name: 'display_name', length: 100)]
    private string $displayName;

    #[ORM\Column(length: 100)]
    private string $username;

    #[ORM\Column(name: 'region_name', length: 255, nullable: true)]
    private ?string $regionName = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $position = null;

    public function getId(): ?int { return $this->id; }

    public function getEventTs(): \DateTimeImmutable { return $this->eventTs; }
    public function setEventTs(\DateTimeImmutable $eventTs): static { $this->eventTs = $eventTs; return $this; }

    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }

    public function getAvatarKey(): string { return $this->avatarKey; }
    public function setAvatarKey(string $avatarKey): static { $this->avatarKey = $avatarKey; return $this; }

    public function getDisplayName(): string { return $this->displayName; }
    public function setDisplayName(string $displayName): static { $this->displayName = $displayName; return $this; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): static { $this->username = $username; return $this; }

    public function getRegionName(): ?string { return $this->regionName; }
    public function setRegionName(?string $regionName): static { $this->regionName = $regionName; return $this; }

    public function getPosition(): ?string { return $this->position; }
    public function setPosition(?string $position): static { $this->position = $position; return $this; }
}
