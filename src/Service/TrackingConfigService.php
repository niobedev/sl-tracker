<?php

namespace App\Service;

use App\Repository\TrackedAvatarRepository;

class TrackingConfigService
{
    private int $version = 0;
    private int $pollInterval = 60;

    public function __construct(
        private readonly TrackedAvatarRepository $trackedAvatarRepository,
    ) {}

    public function getConfig(): array
    {
        $avatars = $this->trackedAvatarRepository->findEnabled();
        $avatarKeys = array_map(fn($a) => $a->getAvatarKey(), $avatars);

        return [
            'trackedAvatars' => $avatarKeys,
            'version' => $this->version,
            'pollInterval' => $this->pollInterval,
        ];
    }

    public function incrementVersion(): void
    {
        $this->version++;
    }

    public function setPollInterval(int $seconds): void
    {
        $this->pollInterval = max(10, $seconds);
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
