<?php

namespace App\Service;

use App\Entity\User;

class LegacyJwtService
{
    public function __construct(
        private readonly string $secret = 'fitopia-dev-jwt-secret-change-in-production-2026',
        private readonly int $expirySeconds = 3600
    ) {
    }

    public function generateAccessToken(User $user): string
    {
        $now = time();
        $payload = [
            'sub' => $user->getEmail() !== '' ? $user->getEmail() : $user->getUsername(),
            'uid' => $user->getId(),
            'role' => $user->getRole() ?? 'Patient',
            'iat' => $now,
            'exp' => $now + $this->expirySeconds,
            'jti' => rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='),
        ];

        $header = [
            'alg' => 'HS256',
            'typ' => 'JWT',
        ];

        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES));
        $signature = $this->sign($encodedHeader . '.' . $encodedPayload);

        return $encodedHeader . '.' . $encodedPayload . '.' . $signature;
    }

    public function getExpirySeconds(): int
    {
        return $this->expirySeconds;
    }

    private function sign(string $value): string
    {
        $signature = hash_hmac('sha256', $value, $this->secret, true);

        return rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
    }

    private function base64UrlEncode(string|false $value): string
    {
        return rtrim(strtr(base64_encode((string) $value), '+/', '-_'), '=');
    }
}
