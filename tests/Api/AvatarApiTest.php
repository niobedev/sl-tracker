<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class AvatarApiTest extends ApiTestCase
{
    public function testListAvatarsReturnsEmptyArray(): void
    {
        $this->apiRequest('GET', '/api/avatars');
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testListAvatarsReturnsAllAvatars(): void
    {
        $this->createTestAvatar('11111111-1111-1111-1111-111111111111');
        $this->createTestAvatar('22222222-2222-2222-2222-222222222222');

        $this->apiRequest('GET', '/api/avatars');
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertCount(2, $response);
    }

    public function testAddAvatarWithValidUuid(): void
    {
        $data = ['avatarKey' => '12345678-1234-1234-1234-123456789012'];
        $this->apiRequest('POST', '/api/avatars', json_encode($data));
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(201, self::$client->getResponse()->getStatusCode());
        $this->assertEquals('12345678-1234-1234-1234-123456789012', $response['avatarKey']);
        $this->assertTrue($response['trackingEnabled']);
    }

    public function testAddAvatarWithInvalidUuidReturns400(): void
    {
        $data = ['avatarKey' => 'not-a-uuid'];
        $this->apiRequest('POST', '/api/avatars', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testAddAvatarMissingKeyReturns400(): void
    {
        $data = [];
        $this->apiRequest('POST', '/api/avatars', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testDeleteAvatar(): void
    {
        $avatar = $this->createTestAvatar('12345678-1234-1234-1234-123456789012');
        
        $this->apiRequest('DELETE', '/api/avatars/12345678-1234-1234-1234-123456789012');
        $this->assertApiResponse(204);
        
        $deleted = $this->entityManager
            ->getRepository(\App\Entity\TrackedAvatar::class)
            ->find('12345678-1234-1234-1234-123456789012');
        $this->assertNull($deleted);
    }

    public function testDeleteNonExistentAvatarReturns404(): void
    {
        $this->apiRequest('DELETE', '/api/avatars/00000000-0000-0000-0000-000000000000');
        $this->assertApiResponse(404);
    }

    public function testPatchAvatarToEnableTracking(): void
    {
        $avatar = $this->createTestAvatar('12345678-1234-1234-1234-123456789012', false);
        
        $data = ['trackingEnabled' => true];
        $this->apiRequest('PATCH', '/api/avatars/12345678-1234-1234-1234-123456789012', json_encode($data));
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertTrue($response['trackingEnabled']);
    }

    public function testPatchAvatarToDisableTracking(): void
    {
        $avatar = $this->createTestAvatar('12345678-1234-1234-1234-123456789012', true);
        
        $data = ['trackingEnabled' => false];
        $this->apiRequest('PATCH', '/api/avatars/12345678-1234-1234-1234-123456789012', json_encode($data));
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertFalse($response['trackingEnabled']);
    }

    public function testPatchAvatarToSetNotificationChannel(): void
    {
        $channel = $this->createTestNotificationChannel('Test', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        $avatar = $this->createTestAvatar('12345678-1234-1234-1234-123456789012');
        
        $data = ['notificationChannelId' => $channel->getId()];
        $this->apiRequest('PATCH', '/api/avatars/12345678-1234-1234-1234-123456789012', json_encode($data));
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertEquals($channel->getId(), $response['notificationChannel']['id']);
    }

    public function testPatchAvatarToRemoveNotificationChannel(): void
    {
        $channel = $this->createTestNotificationChannel('Test', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        $avatar = $this->createTestAvatar('12345678-1234-1234-1234-123456789012');
        $avatar->setNotificationChannel($channel);
        $this->entityManager->flush();
        
        $data = ['notificationChannelId' => null];
        $this->apiRequest('PATCH', '/api/avatars/12345678-1234-1234-1234-123456789012', json_encode($data));
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertNull($response['notificationChannel']);
    }
}
