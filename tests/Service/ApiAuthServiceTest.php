<?php

namespace App\Tests\Service;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Service\ApiAuthService;
use Symfony\Component\HttpFoundation\Request;

class ApiAuthServiceTest extends WebTestCase
{
    private ApiAuthService $service;
    private string $testKey = 'test-key-123';

    protected function setUp(): void
    {
        self::bootKernel();
        $this->service = new ApiAuthService($this->testKey);
    }

    public function testValidApiKeyReturnsTrue(): void
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_API_KEY' => $this->testKey
        ]);

        $result = $this->service->validateApiKey($request);
        $this->assertTrue($result);
    }

    public function testInvalidApiKeyReturnsFalse(): void
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_API_KEY' => 'wrong-key'
        ]);

        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testMissingApiKeyReturnsFalse(): void
    {
        $request = new Request([], [], [], [], [], []);

        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testEmptyApiKeyReturnsFalse(): void
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_API_KEY' => ''
        ]);

        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testApiKeyCaseSensitive(): void
    {
        $request = new Request([], [], [], [], [], [
            'HTTP_X_API-Key' => strtoupper($this->testKey)
        ]);

        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }
}
