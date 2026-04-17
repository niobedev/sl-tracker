<?php

namespace App\Service;

use App\Entity\NotificationChannel;

class NotificationService
{
    public function __construct(
        private readonly TelegramNotifier $telegramNotifier,
        private readonly MatrixNotifier $matrixNotifier,
    ) {}

    public function getNotifier(string $type): NotificationChannelInterface
    {
        return match($type) {
            NotificationChannel::TYPE_TELEGRAM => $this->telegramNotifier,
            NotificationChannel::TYPE_MATRIX => $this->matrixNotifier,
            NotificationChannel::TYPE_DISCORD => throw new \InvalidArgumentException("Discord notifier not yet implemented"),
            default => throw new \InvalidArgumentException("Unknown notification channel type: {$type}")
        };
    }
}
