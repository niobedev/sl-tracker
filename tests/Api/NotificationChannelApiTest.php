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

    public function testCreateChannelWithValidData(): void
    {
        $data = [
            'name' => 'Test Channel',
            'type' => 'telegram',
            'config' => ['bot_token' => 'test', 'chat_id' => '123']
        ];
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));

        $response = json_decode(self::$client->getResponse()->getContent(), true);
        $this->assertEquals(201, self::$client->getResponse()->getStatusCode());
        $this->assertEquals('Test Channel', $response['name']);
        $this->assertEquals('telegram', $response['type']);
        $this->assertTrue($response['enabled']);
    }

    public function testCreateChannelMissingRequiredFieldsReturns400(): void
    {
        $data = ['name' => 'Test'];
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testCreateChannelWithInvalidTypeReturns400(): void
    {
        $data = [
            'name' => 'Test',
            'type' => 'invalid',
            'config' => []
        ];
        $this->apiRequest('POST', '/api/notification-channels', json_encode($data));
        $this->assertApiResponse(400);
    }

    public function testDeleteNonExistentChannelReturns404(): void
    {
        $this->apiRequest('DELETE', '/api/notification-channels/999');
        $this->assertApiResponse(404);
    }

    public function testUpdateNonExistentChannelReturns404(): void
    {
        $data = ['name' => 'Updated'];
        $this->apiRequest('PUT', '/api/notification-channels/999', json_encode($data));
        $this->assertApiResponse(404);
    }

    public function testSendTestNotificationForNonExistentChannelReturns404(): void
    {
        $this->apiRequest('POST', '/api/notification-channels/999/test');
        $this->assertApiResponse(404);
    }
}
