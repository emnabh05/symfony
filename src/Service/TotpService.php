<?php

namespace App\Service;

class TotpService
{
    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        return trim($secret) !== '' && trim($code) !== '' && hash_equals(trim($secret), trim($code));
    }
}
