<?php

namespace App\Tests\Service;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Service\ApiAuthService;

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
        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('headers')->willReturn(new \Symfony\Component\HttpFoundation\HeaderBag([
            'X-API-Key' => $this->testKey
        ]));
        
        $result = $this->service->validateApiKey($request);
        $this->assertTrue($result);
    }

    public function testInvalidApiKeyReturnsFalse(): void
    {
        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('headers')->willReturn(new \Symfony\Component\HttpFoundation\HeaderBag([
            'X-API-Key' => 'wrong-key'
        ]));
        
        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testMissingApiKeyReturnsFalse(): void
    {
        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('headers')->willReturn(new \Symfony\Component\HttpFoundation\HeaderBag([]));
        
        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testEmptyApiKeyReturnsFalse(): void
    {
        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('headers')->willReturn(new \Symfony\Component\HttpFoundation\HeaderBag([
            'X-API-Key' => ''
        ]));
        
        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }

    public function testApiKeyCaseSensitive(): void
    {
        $request = $this->createMock(\Symfony\Component\HttpFoundation\Request::class);
        $request->method('headers')->willReturn(new \Symfony\Component\HttpFoundation\HeaderBag([
            'X-API-Key' => strtoupper($this->testKey)
        ]));
        
        $result = $this->service->validateApiKey($request);
        $this->assertFalse($result);
    }
}
