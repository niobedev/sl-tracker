<?php

namespace App\Service;

use App\Entity\NotificationChannel;

class MatrixNotifier extends AbstractNotifier
{
    protected function parseConfig(array $config): array
    {
        $serverUrl = $config['server_url'] ?? null;
        $roomId = $config['room_id'] ?? null;
        $botToken = $config['bot_token'] ?? null;

        return [
            'valid' => (bool) $serverUrl && $roomId && $botToken,
            'server_url' => $serverUrl,
            'room_id' => $roomId,
            'bot_token' => $botToken,
        ];
    }

    protected function send(string $avatarKey, string $action, string $message, array $event, array $parsedConfig): bool
    {
        $context = [
            'channel_type' => 'matrix',
            'server_url' => $parsedConfig['server_url'],
            'room_id' => $parsedConfig['room_id'],
            'avatar_key' => $avatarKey,
            'action' => $action,
            'event' => $event,
            'message_body' => $message,
        ];

        try {
            $txnId = md5($parsedConfig['room_id'] . time() . random_bytes(4));
            $url = rtrim($parsedConfig['server_url'], '/') . "/_matrix/client/v3/rooms/{$parsedConfig['room_id']}/send/m.room.message/{$txnId}";

            $response = $this->httpClient->request('PUT', $url, [
                'headers' => [
                    'Authorization' => "Bearer {$parsedConfig['bot_token']}",
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

    public function test(NotificationChannel $channel): bool
    {
        $config = $channel->getConfig();
        $parsedConfig = $this->parseConfig($config);

        if (!$parsedConfig['valid']) {
            return false;
        }

        $message = "✅ Test notification from Avatar Tracking System";
        return $this->send($channel->getName(), 'test', $message, [], $parsedConfig);
    }
}