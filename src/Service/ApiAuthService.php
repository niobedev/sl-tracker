<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class ApiAuthService
{
    private string $secretKey;

    public function __construct(string $secretKey)
    {
        $this->secretKey = $secretKey;
    }

    public function validateApiKey(Request $request): bool
    {
        $apiKey = $request->headers->get('X-API-Key');
        return $apiKey === $this->secretKey;
    }
}
