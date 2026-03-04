<?php

namespace App\Service;

class TotpService
{
    private const BASE32_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public function generateSecret(int $length = 32): string
    {
        $alphabet = self::BASE32_ALPHABET;
        $max = strlen($alphabet) - 1;
        $secret = '';

        for ($i = 0; $i < $length; $i++) {
            $secret .= $alphabet[random_int(0, $max)];
        }

        return $secret;
    }

    public function verifyCode(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $time = time();
        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->getCode($secret, $time + ($offset * 30)), $code)) {
                return true;
            }
        }

        return false;
    }

    public function getProvisioningUri(string $issuer, string $account, string $secret): string
    {
        $issuer = trim($issuer);
        $account = trim($account);

        $label = rawurlencode($issuer.':'.$account);
        $issuerParam = rawurlencode($issuer);
        $secretParam = rawurlencode($secret);

        return sprintf(
            'otpauth://totp/%s?secret=%s&issuer=%s&algorithm=SHA1&digits=6&period=30',
            $label,
            $secretParam,
            $issuerParam
        );
    }

    private function getCode(string $secret, int $timestamp): string
    {
        $counter = intdiv($timestamp, 30);
        $binaryCounter = pack('N*', 0, $counter);
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return '000000';
        }

        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $binary = (
            ((ord($hash[$offset]) & 0x7f) << 24) |
            ((ord($hash[$offset + 1]) & 0xff) << 16) |
            ((ord($hash[$offset + 2]) & 0xff) << 8) |
            (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($binary % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }

        $alphabet = array_flip(str_split(self::BASE32_ALPHABET));
        $bits = '';

        foreach (str_split($secret) as $char) {
            if (!isset($alphabet[$char])) {
                return '';
            }
            $bits .= str_pad(decbin($alphabet[$char]), 5, '0', STR_PAD_LEFT);
        }

        $binary = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $binary .= chr(bindec($byte));
            }
        }

        return $binary;
    }
}
