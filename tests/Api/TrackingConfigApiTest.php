<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class TrackingConfigApiTest extends ApiTestCase
{
    public function testGetTrackingConfigReturnsEmptyArrayWhenNoAvatars(): void
    {
        $this->apiRequest('GET', '/api/tracking-config');
        $this->assertApiResponse(200, [
            'trackedAvatars' => [],
            'version' => 0,
            'pollInterval' => 60
        ]);
    }

    public function testGetTrackingConfigReturnsEnabledAvatars(): void
    {
        $this->createTestAvatar('11111111-1111-1111-1111-111111111111', true);
        $this->createTestAvatar('22222222-2222-2222-2222-222222222222', true);
        $this->createTestAvatar('33333333-3333-3333-3333-333333333333', false);

        $this->apiRequest('GET', '/api/tracking-config');
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertCount(2, $response['trackedAvatars']);
        $this->assertContains('11111111-1111-1111-1111-111111111111', $response['trackedAvatars']);
        $this->assertContains('22222222-2222-2222-2222-222222222222', $response['trackedAvatars']);
        $this->assertNotContains('33333333-3333-3333-3333-333333333333', $response['trackedAvatars']);
        $this->assertArrayHasKey('version', $response);
        $this->assertEquals(60, $response['pollInterval']);
    }

    public function testGetTrackingConfigWithoutAuthReturns401(): void
    {
        $this->apiRequest('GET', '/api/tracking-config', withApiKey: false);
        $this->assertApiResponse(401);
    }
}
