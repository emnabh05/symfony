<?php

namespace App\Service;

class FacePlusPlusCompareService
{
    public function providerStatus(): array
    {
        return [
            'ok' => $this->isConfigured(),
            'provider' => 'local-fallback',
            'message' => $this->isConfigured()
                ? 'Face ID fallback provider ready.'
                : 'Face ID provider not configured.',
        ];
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function compareFaceTokens(string $referenceToken, string $candidateToken): bool
    {
        return hash_equals(trim($referenceToken), trim($candidateToken));
    }

    public function detectFaceTokenFromBase64(string $imageBase64): string
    {
        $imageBase64 = trim($imageBase64);
        if ($imageBase64 === '') {
            throw new \RuntimeException('No face detected.');
        }

        return hash('sha256', $imageBase64);
    }
}
