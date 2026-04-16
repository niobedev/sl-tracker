<?php

namespace App\Service;

class NotificationService
{
    public function __construct(
        private readonly TelegramNotifier $telegramNotifier,
    ) {}

    public function getNotifier(string $type): NotificationChannelInterface
    {
        return match($type) {
            NotificationChannel::TYPE_TELEGRAM => $this->telegramNotifier,
            NotificationChannel::TYPE_DISCORD => throw new \InvalidArgumentException("Discord notifier not yet implemented"),
            NotificationChannel::TYPE_MATRIX => throw new \InvalidArgumentException("Matrix notifier not yet implemented"),
            default => throw new \InvalidArgumentException("Unknown notification channel type: {$type}")
        };
    }
}
