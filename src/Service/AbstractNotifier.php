<?php

namespace App\Service;

use App\Entity\TrackedAvatar;
use App\Repository\AvatarProfileRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

abstract class AbstractNotifier implements NotificationChannelInterface
{
    protected HttpClientInterface $httpClient;
    protected AvatarProfileRepository $profileRepository;
    protected LoggerInterface $logger;

    public function __construct(
        HttpClientInterface $httpClient,
        AvatarProfileRepository $profileRepository,
        LoggerInterface $logger,
    ) {
        $this->httpClient = $httpClient;
        $this->profileRepository = $profileRepository;
        $this->logger = $logger;
    }

    public function sendLogin(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $parsedConfig = $this->parseConfig($config);
        if (!$parsedConfig['valid']) {
            return;
        }

        $avatarKey = $avatar->getAvatarKey();
        $name = $avatarKey;
        $username = '';

        $profile = $this->profileRepository->find($avatarKey);
        if ($profile) {
            if ($profile->getName()) {
                $name = $profile->getName();
            }
            if ($profile->getUsername()) {
                $username = $profile->getUsername();
            }
        }

        $message = sprintf(
            "🟢 %s (%s) has logged in.",
            $name,
            $username ?: 'unknown'
        );

        $this->send($avatarKey, 'login', $message, $event, $parsedConfig);
    }

    public function sendLogout(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $parsedConfig = $this->parseConfig($config);
        if (!$parsedConfig['valid']) {
            return;
        }

        $avatarKey = $avatar->getAvatarKey();
        $name = $avatarKey;
        $username = '';

        $profile = $this->profileRepository->find($avatarKey);
        if ($profile) {
            if ($profile->getName()) {
                $name = $profile->getName();
            }
            if ($profile->getUsername()) {
                $username = $profile->getUsername();
            }
        }

        $message = sprintf(
            "🔴 %s (%s) has logged out.",
            $name,
            $username ?: 'unknown'
        );

        $this->send($avatarKey, 'logout', $message, $event, $parsedConfig);
    }

    abstract protected function parseConfig(array $config): array;

    abstract protected function send(string $avatarKey, string $action, string $message, array $event, array $parsedConfig): bool;
}