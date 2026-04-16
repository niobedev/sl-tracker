<?php

namespace App\Entity;

use App\Repository\AvatarReminderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvatarReminderRepository::class)]
#[ORM\Table(name: 'avatar_reminder')]
#[ORM\Index(columns: ['avatar_key'], name: 'idx_reminder_avatar_key')]
#[ORM\Index(columns: ['resolved_at'], name: 'idx_reminder_resolved')]
class AvatarReminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'avatar_key', length: 36)]
    private string $avatarKey;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(name: 'reminder_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $reminderAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'resolved_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $resolvedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'resolved_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $resolvedBy = null;

    public function getId(): ?int { return $this->id; }

    public function getAvatarKey(): string { return $this->avatarKey; }
    public function setAvatarKey(string $avatarKey): static { $this->avatarKey = $avatarKey; return $this; }

    public function getContent(): string { return $this->content; }
    public function setContent(string $content): static { $this->content = $content; return $this; }

    public function getReminderAt(): \DateTimeImmutable { return $this->reminderAt; }
    public function setReminderAt(\DateTimeImmutable $reminderAt): static { $this->reminderAt = $reminderAt; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }

    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): static { $this->author = $author; return $this; }

    public function getResolvedAt(): ?\DateTimeImmutable { return $this->resolvedAt; }
    public function setResolvedAt(?\DateTimeImmutable $resolvedAt): static { $this->resolvedAt = $resolvedAt; return $this; }

    public function getResolvedBy(): ?User { return $this->resolvedBy; }
    public function setResolvedBy(?User $resolvedBy): static { $this->resolvedBy = $resolvedBy; return $this; }

    public function isResolved(): bool
    {
        return $this->resolvedAt !== null;
    }

    public function isOverdue(): bool
    {
        return !$this->isResolved()
            && $this->reminderAt < new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
