<?php

namespace App\Entity;

use App\Repository\AvatarProfileRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvatarProfileRepository::class)]
#[ORM\Table(name: 'avatar_profile')]
class AvatarProfile
{
    #[ORM\Id]
    #[ORM\Column(name: 'avatar_key', length: 36)]
    private string $avatarKey;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(name: 'image_url', type: Types::TEXT, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(name: 'bio_html', type: Types::TEXT, nullable: true)]
    private ?string $bioHtml = null;

    #[ORM\Column(name: 'image_data', type: Types::BLOB, nullable: true)]
    private mixed $imageData = null;

    #[ORM\Column(name: 'synced_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $syncedAt;

    public function getAvatarKey(): string { return $this->avatarKey; }
    public function setAvatarKey(string $avatarKey): static { $this->avatarKey = $avatarKey; return $this; }

    public function getName(): ?string { return $this->name; }
    public function setName(?string $name): static { $this->name = $name; return $this; }

    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }

    public function getBioHtml(): ?string { return $this->bioHtml; }
    public function setBioHtml(?string $bioHtml): static { $this->bioHtml = $bioHtml; return $this; }

    /** Doctrine returns BLOB columns as PHP streams; this normalises to string. */
    public function getImageData(): ?string
    {
        if ($this->imageData === null) {
            return null;
        }
        if (is_resource($this->imageData)) {
            return stream_get_contents($this->imageData);
        }
        return $this->imageData;
    }

    public function setImageData(?string $imageData): static { $this->imageData = $imageData; return $this; }

    public function getSyncedAt(): \DateTimeImmutable { return $this->syncedAt; }
    public function setSyncedAt(\DateTimeImmutable $syncedAt): static { $this->syncedAt = $syncedAt; return $this; }
}
