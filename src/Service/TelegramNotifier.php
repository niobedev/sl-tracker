<?php

namespace App\Service;

use App\Entity\NotificationChannel;
use App\Entity\TrackedAvatar;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TelegramNotifier implements NotificationChannelInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function sendLogin(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return;
        }

        $profile = $avatar->getAvatarKey();
        $name = $avatar->getAvatarKey();
        $username = '';

        // Try to get profile info from the event
        if (isset($event['displayName'])) {
            $name = $event['displayName'];
        }
        if (isset($event['username'])) {
            $username = $event['username'];
        }

        $message = sprintf(
            "🟢 %s has logged in to Second Life\n%s",
            $name,
            $username ? "Username: @{$username}" : ''
        );

        $this->sendMessage($botToken, $chatId, $message);
    }

    public function sendLogout(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return;
        }

        $name = $avatar->getAvatarKey();
        $username = '';

        if (isset($event['displayName'])) {
            $name = $event['displayName'];
        }
        if (isset($event['username'])) {
            $username = $event['username'];
        }

        $message = sprintf(
            "🔴 %s has logged out of Second Life\n%s",
            $name,
            $username ? "Username: @{$username}" : ''
        );

        $this->sendMessage($botToken, $chatId, $message);
    }

    public function test(NotificationChannel $channel): bool
    {
        $config = $channel->getConfig();
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        if (!$botToken || !$chatId) {
            return false;
        }

        $message = "✅ Test notification from Avatar Tracking System";
        return $this->sendMessage($botToken, $chatId, $message);
    }

    private function sendMessage(string $botToken, string $chatId, string $message): bool
    {
        try {
            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$botToken}/sendMessage", [
                'json' => [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ],
                'timeout' => 5,
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Throwable) {
            return false;
        }
    }
}
