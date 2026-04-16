<?php

namespace App\Service;

use App\Entity\NotificationChannel;
use App\Entity\TrackedAvatar;

interface NotificationChannelInterface
{
    public function sendLogin(TrackedAvatar $avatar, array $event): void;
    public function sendLogout(TrackedAvatar $avatar, array $event): void;
    public function test(NotificationChannel $channel): bool;
}
