<?php

namespace App\Service;

use App\Entity\AvatarProfile;
use App\Entity\TrackedAvatar;
use App\Repository\AvatarProfileRepository;
use App\Repository\TrackedAvatarRepository;
use Doctrine\ORM\EntityManagerInterface;

class AvatarTrackingService
{
    public function __construct(
        private readonly SecondLifeProfileService $secondLifeProfileService,
        private readonly TrackedAvatarRepository $trackedAvatarRepository,
        private readonly AvatarProfileRepository $avatarProfileRepository,
        private readonly EntityManagerInterface $em,
        private readonly TrackingConfigService $trackingConfigService,
    ) {}

    public function addAvatar(string $avatarKey): TrackedAvatar
    {
        $avatarKey = strtolower($avatarKey);

        $existing = $this->trackedAvatarRepository->find($avatarKey);
        if ($existing) {
            throw new \InvalidArgumentException("Avatar {$avatarKey} is already being tracked");
        }

        $this->validateUuid($avatarKey);

        $profile = $this->secondLifeProfileService->fetchProfile($avatarKey, forceRefresh: true);
        if (!$profile) {
            throw new \RuntimeException("Could not fetch profile for avatar {$avatarKey}");
        }

        $avatar = new TrackedAvatar();
        $avatar->setAvatarKey($avatarKey);
        $this->em->persist($avatar);
        $this->em->flush();

        $this->trackingConfigService->incrementVersion();

        return $avatar;
    }

    public function removeAvatar(string $avatarKey): void
    {
        $avatar = $this->trackedAvatarRepository->find(strtolower($avatarKey));
        if (!$avatar) {
            throw new \RuntimeException("Avatar {$avatarKey} is not being tracked");
        }

        $this->em->remove($avatar);
        $this->em->flush();

        $this->trackingConfigService->incrementVersion();
    }

    public function toggleTracking(string $avatarKey, bool $enabled): void
    {
        $avatar = $this->trackedAvatarRepository->findOneByAvatarKey($avatarKey);
        if (!$avatar) {
            throw new \RuntimeException("Avatar {$avatarKey} not found");
        }

        $wasEnabled = $avatar->isTrackingEnabled();
        $avatar->setTrackingEnabled($enabled);
        $this->em->flush();

        if ($wasEnabled !== $enabled) {
            $this->trackingConfigService->incrementVersion();
        }
    }

    public function setNotificationChannel(string $avatarKey, ?int $channelId): void
    {
        $avatar = $this->trackedAvatarRepository->findOneByAvatarKey($avatarKey);
        if (!$avatar) {
            throw new \RuntimeException("Avatar {$avatarKey} not found");
        }

        if ($channelId === null) {
            $avatar->setNotificationChannel(null);
        } else {
            $channel = $this->em->find(\App\Entity\NotificationChannel::class, $channelId);
            if (!$channel) {
                throw new \RuntimeException("Notification channel not found");
            }
            $avatar->setNotificationChannel($channel);
        }

        $this->em->flush();
    }

    public function getAvatarWithProfile(string $avatarKey): ?array
    {
        $avatar = $this->trackedAvatarRepository->findOneWithProfile($avatarKey);
        if (!$avatar) {
            return null;
        }

        $profile = $this->avatarProfileRepository->find($avatar->getAvatarKey());

        return [
            'avatar' => $avatar,
            'profile' => $profile,
        ];
    }

    public function getAvatarsWithProfiles(): array
    {
        $avatars = $this->trackedAvatarRepository->findAllWithProfile();
        $result = [];

        foreach ($avatars as $avatar) {
            $profile = $this->avatarProfileRepository->find($avatar->getAvatarKey());
            $result[] = [
                'avatar' => $avatar,
                'profile' => $profile,
            ];
        }

        return $result;
    }

    private function validateUuid(string $uuid): void
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            throw new \InvalidArgumentException("Invalid UUID format: {$uuid}");
        }
    }
}
