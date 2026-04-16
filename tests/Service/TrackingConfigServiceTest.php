<?php

namespace App\Tests\Service;

use App\Tests\ApiTestCase;
use App\Entity\TrackedAvatar;

class TrackingConfigServiceTest extends ApiTestCase
{
    private $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = self::$client->getContainer()->get(\App\Service\TrackingConfigService::class);
    }

    public function testGetConfigReturnsEmptyWhenNoAvatars(): void
    {
        $config = $this->service->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('trackedAvatars', $config);
        $this->assertArrayHasKey('version', $config);
        $this->assertArrayHasKey('pollInterval', $config);
        $this->assertEmpty($config['trackedAvatars']);
        $this->assertEquals(0, $config['version']);
        $this->assertEquals(60, $config['pollInterval']);
    }

    public function testGetConfigReturnsOnlyEnabledAvatars(): void
    {
        $avatar1 = new TrackedAvatar();
        $avatar1->setAvatarKey('11111111-1111-1111-1111-111111111111');
        $avatar1->setTrackingEnabled(true);
        $this->entityManager->persist($avatar1);

        $avatar2 = new TrackedAvatar();
        $avatar2->setAvatarKey('22222222-2222-2222-2222-222222222222');
        $avatar2->setTrackingEnabled(false);
        $this->entityManager->persist($avatar2);

        $this->entityManager->flush();

        $config = $this->service->getConfig();

        $this->assertCount(1, $config['trackedAvatars']);
        $this->assertContains('11111111-1111-1111-1111-111111111111', $config['trackedAvatars']);
        $this->assertNotContains('22222222-2222-2222-2222-222222222222', $config['trackedAvatars']);
    }

    public function testIncrementVersionIncreasesVersionNumber(): void
    {
        $config1 = $this->service->getConfig();
        $version1 = $config1['version'];

        $this->service->incrementVersion();

        $config2 = $this->service->getConfig();
        $version2 = $config2['version'];

        $this->assertGreaterThan($version1, $version2);
    }

    public function testIncrementVersionMultipleTimes(): void
    {
        $version1 = $this->service->getVersion();

        $this->service->incrementVersion();
        $this->service->incrementVersion();
        $this->service->incrementVersion();

        $version2 = $this->service->getVersion();

        $this->assertEquals($version1 + 3, $version2);
    }

    public function testSetPollInterval(): void
    {
        $this->service->setPollInterval(30);
        $config = $this->service->getConfig();

        $this->assertEquals(30, $config['pollInterval']);
    }

    public function testSetPollIntervalMinimumValue(): void
    {
        $this->service->setPollInterval(5);
        $config = $this->service->getConfig();

        // Should be clamped to minimum of 10
        $this->assertEquals(10, $config['pollInterval']);
    }

    public function testSetPollIntervalLargeValue(): void
    {
        $this->service->setPollInterval(300);
        $config = $this->service->getConfig();

        $this->assertEquals(300, $config['pollInterval']);
    }
}
