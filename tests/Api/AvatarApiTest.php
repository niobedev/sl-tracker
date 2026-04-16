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

    public function testAddAvatarDuplicateReturns409(): void
    {
        $data = ['avatarKey' => '12345678-1234-1234-1234-123456789012'];

        $this->apiRequest('POST', '/api/avatars', json_encode($data));
        $this->assertEquals(201, self::$client->getResponse()->getStatusCode());

        $this->apiRequest('POST', '/api/avatars', json_encode($data));
        $this->assertEquals(409, self::$client->getResponse()->getStatusCode());
    }

    public function testDeleteNonExistentAvatarReturns404(): void
    {
        $this->apiRequest('DELETE', '/api/avatars/00000000-0000-0000-0000-000000000000');
        $this->assertApiResponse(404);
    }

    public function testPatchNonExistentAvatarReturns404(): void
    {
        $data = ['trackingEnabled' => true];
        $this->apiRequest('PATCH', '/api/avatars/00000000-0000-0000-0000-000000000000', json_encode($data));
        $this->assertApiResponse(404);
    }
}
