<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class EventsApiTest extends ApiTestCase
{
    public function testPostEventsWithValidDataReturns201(): void
    {
        $events = [[
            'event_ts' => '2026-04-16T12:00:00Z',
            'action' => 'login',
            'avatarKey' => '12345678-1234-1234-1234-123456789012',
            'displayName' => 'Test User',
            'username' => 'testuser',
            'regionName' => 'global'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($events));
        $this->assertApiResponse(201, ['received' => 1]);

        $events = $this->entityManager
            ->getRepository(\App\Entity\Event::class)
            ->findAll();
        $this->assertCount(1, $events);
    }

    public function testPostEventsWithoutApiKeyReturns401(): void
    {
        $this->apiRequest('POST', '/api/events', '[]', withApiKey: false);
        $this->assertApiResponse(401);
    }

    public function testPostEventsWithInvalidActionReturns400(): void
    {
        $events = [[
            'event_ts' => '2026-04-16T12:00:00Z',
            'action' => 'invalid',
            'avatarKey' => '12345678-1234-1234-1234-123456789012',
            'displayName' => 'Test User',
            'username' => 'testuser'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($events));
        $this->assertApiResponse(400);
    }

    public function testPostEventsWithInvalidUuidReturns400(): void
    {
        $events = [[
            'event_ts' => '2026-04-16T12:00:00Z',
            'action' => 'login',
            'avatarKey' => 'not-a-uuid',
            'displayName' => 'Test User',
            'username' => 'testuser'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($events));
        $this->assertApiResponse(400);
    }

    public function testPostEventsWithMissingRequiredFieldReturns400(): void
    {
        $events = [[
            'action' => 'login',
            'avatarKey' => '12345678-1234-1234-1234-123456789012'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($events));
        $this->assertApiResponse(400);
    }

    public function testPostBatchEvents(): void
    {
        $events = [
            [
                'event_ts' => '2026-04-16T12:00:00Z',
                'action' => 'login',
                'avatarKey' => '12345678-1234-1234-1234-123456789012',
                'displayName' => 'User One',
                'username' => 'user1'
            ],
            [
                'event_ts' => '2026-04-16T12:05:00Z',
                'action' => 'logout',
                'avatarKey' => '87654321-4321-4321-4321-210987654321',
                'displayName' => 'User Two',
                'username' => 'user2'
            ]
        ];

        $this->apiRequest('POST', '/api/events', json_encode($events));
        $this->assertApiResponse(201, ['received' => 2]);
    }

    public function testPostEventsAcceptsLoginAndLogout(): void
    {
        $loginEvent = [[
            'event_ts' => '2026-04-16T12:00:00Z',
            'action' => 'login',
            'avatarKey' => '12345678-1234-1234-1234-123456789012',
            'displayName' => 'Test User',
            'username' => 'testuser'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($loginEvent));
        $this->assertApiResponse(201);

        $logoutEvent = [[
            'event_ts' => '2026-04-16T13:00:00Z',
            'action' => 'logout',
            'avatarKey' => '12345678-1234-1234-1234-123456789012',
            'displayName' => 'Test User',
            'username' => 'testuser'
        ]];

        $this->apiRequest('POST', '/api/events', json_encode($logoutEvent));
        $this->assertApiResponse(201);

        $events = $this->entityManager
            ->getRepository(\App\Entity\Event::class)
            ->findAll();
        $this->assertCount(2, $events);
    }
}
