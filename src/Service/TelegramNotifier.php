<?php

namespace App\Service;

use App\Entity\NotificationChannel;

class TelegramNotifier extends AbstractNotifier
{
    protected function parseConfig(array $config): array
    {
        $botToken = $config['bot_token'] ?? null;
        $chatId = $config['chat_id'] ?? null;

        return [
            'valid' => (bool) $botToken && $chatId,
            'bot_token' => $botToken,
            'chat_id' => $chatId,
        ];
    }

    protected function send(string $avatarKey, string $action, string $message, array $event, array $parsedConfig): bool
    {
        $context = [
            'channel_type' => 'telegram',
            'channel_id' => $parsedConfig['chat_id'],
            'avatar_key' => $avatarKey,
            'action' => $action,
            'event' => $event,
            'message_body' => $message,
        ];

        try {
            $response = $this->httpClient->request('POST', "https://api.telegram.org/bot{$parsedConfig['bot_token']}/sendMessage", [
                'json' => [
                    'chat_id' => $parsedConfig['chat_id'],
                    'text' => $message,
                    'parse_mode' => 'HTML'
                ],
                'timeout' => 5,
            ]);

            $statusCode = $response->getStatusCode();
            $responseData = $response->toArray(false);

            if ($statusCode === 200) {
                $this->logger->info('Telegram notification sent successfully', array_merge($context, [
                    'status' => 'success',
                    'telegram_message_id' => $responseData['result']['message_id'] ?? null,
                ]));
                return true;
            }

            $this->logger->error('Telegram notification failed', array_merge($context, [
                'status' => 'failed',
                'status_code' => $statusCode,
                'response' => $responseData,
            ]));
            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Telegram notification exception', array_merge($context, [
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