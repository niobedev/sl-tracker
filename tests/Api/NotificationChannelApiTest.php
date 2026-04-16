<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class NotificationChannelApiTest extends ApiTestCase
{
    public function testListChannelsReturnsEmptyArray(): void
    {
        $this->apiRequest('GET', '/api/notification-channels');
        
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertIsArray($response);
        $this->assertEmpty($response);
    }

    public function testCreateTelegramChannel(): void
    {
        $data = [
            'name' => 'My Telegram',
            'type' => 'telegram',
            'config' => [
                'bot_token' => '123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11',
                'chat_id' => '123456789'
            ]
        ];
        
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        
        $this->assertEquals(201, self::$client->getResponse()->getStatusCode());
        $this->assertEquals('My Telegram', $response['name']);
        $this->assertEquals('telegram', $response['type']);
        $this->assertEquals('123456:ABC-DEF1234ghIkl-zyx57W2v1u123ew11', $response['config']['bot_token']);
        $this->assertEquals('123456789', $response['config']['chat_id']);
        $this->assertTrue($response['enabled']);
    }

    public function testCreateChannelWithInvalidTypeReturns400(): void
    {
        $data = [
            'name' => 'Invalid',
            'type' => 'invalid_type',
            'config' => []
        ];
        
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testCreateChannelMissingFieldsReturns400(): void
    {
        $data = [
            'name' => 'Test'
            // Missing type and config
        ];
        
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testUpdateChannel(): void
    {
        $channel = $this->createTestNotificationChannel('Old Name', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        
        $data = [
            'name' => 'New Name',
            'config' => ['bot_token' => 'y', 'chat_id' => '2']
        ];
        
        $this->apiRequest('PUT', '/api/notification-channels/' . $channel->getId(), json_encode($data));
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertEquals('New Name', $response['name']);
        $this->assertEquals('y', $response['config']['bot_token']);
    }

    public function testUpdateChannelEnable(): void
    {
        $channel = $this->createTestNotificationChannel('Test', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        $channel->setEnabled(false);
        $this->entityManager->flush();
        
        $data = ['enabled' => true];
        $this->apiRequest('PUT', '/api/notification-channels/' . $channel->getId(), json_encode($data));
        $response = json_decode(self::$client->getResponse()->getContent(), true);
        
        $this->assertEquals(200, self::$client->getResponse()->getStatusCode());
        $this->assertTrue($response['enabled']);
    }

    public function testDeleteChannel(): void
    {
        $channel = $this->createTestNotificationChannel('Test', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        
        $this->apiRequest('DELETE', '/api/notification-channels/' . $channel->getId());
        $this->assertApiResponse(204);
        
        $deleted = $this->entityManager
            ->getRepository(\App\Entity\NotificationChannel::class)
            ->find($channel->getId());
        $this->assertNull($deleted);
    }

    public function testDeleteNonExistentChannelReturns404(): void
    {
        $this->apiRequest('DELETE', '/api/notification-channels/999999');
        $this->assertApiResponse(404);
    }

    public function testSendTestNotification(): void
    {
        $channel = $this->createTestNotificationChannel('Test', 'telegram', ['bot_token' => 'x', 'chat_id' => '1']);
        
        $this->apiRequest('POST', '/api/notification-channels/' . $channel->getId() . '/test');
        
        $response = self::$client->getResponse();
        $this->assertEquals(200, $response->getStatusCode());
        
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('success', $data);
        $this->assertIsBool($data['success']);
    }

    public function testSendTestNotificationForInvalidTypeReturns400(): void
    {
        $channel = new \App\Entity\NotificationChannel();
        $channel->setName('Test');
        $channel->setType('discord');
        $channel->setConfig([]);
        $this->entityManager->persist($channel);
        $this->entityManager->flush();
        
        $this->apiRequest('POST', '/api/notification-channels/' . $channel->getId() . '/test');
        $this->assertApiResponse(400);
    }
}
