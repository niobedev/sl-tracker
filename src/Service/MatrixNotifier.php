<?php

namespace App\Service;

use App\Entity\NotificationChannel;
use App\Entity\TrackedAvatar;
use App\Repository\AvatarProfileRepository;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[WithMonologChannel('notification')]
class MatrixNotifier implements NotificationChannelInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AvatarProfileRepository $profileRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function sendLogin(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $serverUrl = $config['server_url'] ?? null;
        $roomId = $config['room_id'] ?? null;
        $botToken = $config['bot_token'] ?? null;

        if (!$serverUrl || !$roomId || !$botToken) {
            return;
        }

        $avatarKey = $avatar->getAvatarKey();
        $name = $avatarKey;
        $username = '';

        if (isset($event['displayName'])) {
            $name = $event['displayName'];
        }
        if (isset($event['username'])) {
            $username = $event['username'];
        } else {
            $profile = $this->profileRepository->find($avatarKey);
            if ($profile && $profile->getUsername()) {
                $username = $profile->getUsername();
            }
        }

        $message = sprintf(
            "🟢 %s (%s) has logged in.",
            $name,
            $username ?: 'unknown'
        );

        $this->sendMessage($avatarKey, 'login', $serverUrl, $roomId, $botToken, $message, $event);
    }

    public function sendLogout(TrackedAvatar $avatar, array $event): void
    {
        $channel = $avatar->getNotificationChannel();
        if (!$channel || !$channel->isEnabled()) {
            return;
        }

        $config = $channel->getConfig();
        $serverUrl = $config['server_url'] ?? null;
        $roomId = $config['room_id'] ?? null;
        $botToken = $config['bot_token'] ?? null;

        if (!$serverUrl || !$roomId || !$botToken) {
            return;
        }

        $avatarKey = $avatar->getAvatarKey();
        $name = $avatarKey;
        $username = '';

        if (isset($event['displayName'])) {
            $name = $event['displayName'];
        }
        if (isset($event['username'])) {
            $username = $event['username'];
        } else {
            $profile = $this->profileRepository->find($avatarKey);
            if ($profile && $profile->getUsername()) {
                $username = $profile->getUsername();
            }
        }

        $message = sprintf(
            "🔴 %s (%s) has logged out.",
            $name,
            $username ?: 'unknown'
        );

        $this->sendMessage($avatarKey, 'logout', $serverUrl, $roomId, $botToken, $message, $event);
    }

    public function test(NotificationChannel $channel): bool
    {
        $config = $channel->getConfig();
        $serverUrl = $config['server_url'] ?? null;
        $roomId = $config['room_id'] ?? null;
        $botToken = $config['bot_token'] ?? null;

        if (!$serverUrl || !$roomId || !$botToken) {
            return false;
        }

        $message = "✅ Test notification from Avatar Tracking System";
        return $this->sendMessage($channel->getName(), 'test', $serverUrl, $roomId, $botToken, $message, []);
    }

    private function sendMessage(string $avatarKey, string $action, string $serverUrl, string $roomId, string $botToken, string $message, array $event): bool
    {
        $context = [
            'channel_type' => 'matrix',
            'server_url' => $serverUrl,
            'room_id' => $roomId,
            'avatar_key' => $avatarKey,
            'action' => $action,
            'event' => $event,
            'message_body' => $message,
        ];

        try {
            $txnId = md5($roomId . time() . random_bytes(4));
            $url = rtrim($serverUrl, '/') . "/_matrix/client/v3/rooms/{$roomId}/send/m.room.message/{$txnId}";

            $response = $this->httpClient->request('PUT', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$botToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'msgtype' => 'm.text',
                    'body' => $message,
                ],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('Matrix notification sent successfully', array_merge($context, [
                    'status' => 'success',
                    'txn_id' => $txnId,
                ]));
                return true;
            }

            $responseData = $response->toArray(false);
            $this->logger->error('Matrix notification failed', array_merge($context, [
                'status' => 'failed',
                'status_code' => $statusCode,
                'response' => $responseData,
            ]));
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Matrix notification exception', array_merge($context, [
                'status' => 'failed',
                'exception' => $e->getMessage(),
            ]));
            return false;
        }
    }
}
