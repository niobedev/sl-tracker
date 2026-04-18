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
        $apiKey = $request->headers->get('X-API-Key') 
            ?? $request->headers->get('x-api-key')
            ?? $request->headers->get('X-Api-Key');
        if (empty($apiKey)) {
            error_log('ApiAuth: No API key received');
            return false;
        }
        $valid = $apiKey === $this->secretKey;
        if (!$valid) {
            error_log('ApiAuth: Key mismatch: received=' . substr($apiKey, 0, 4) . '... expected=' . substr($this->secretKey, 0, 4) . '...');
        }
        return $valid;
    }
}
