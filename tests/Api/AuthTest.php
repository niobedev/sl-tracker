<?php

namespace App\Tests\Api;

use App\Tests\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function testApiEndpointWithoutApiKeyReturns401(): void
    {
        $this->apiRequest('GET', '/api/tracking-config', withApiKey: false);
        $this->assertApiResponse(401);
    }

    public function testApiEndpointWithInvalidApiKeyReturns401(): void
    {
        self::$client->request('GET', '/api/tracking-config', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_API_KEY' => 'wrong-key'
        ]);
        $this->assertApiResponse(401);
    }

    public function testApiEndpointWithValidApiKeyReturns200(): void
    {
        $this->apiRequest('GET', '/api/tracking-config');
        $this->assertApiResponse(200);
    }
}
