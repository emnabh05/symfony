<?php

namespace App\Service;

class RecaptchaVerifier
{
    private const GOOGLE_TEST_SECRET = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

    public function __construct(
        private readonly string $secretKey,
        private readonly string $kernelEnvironment = 'prod'
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->secretKey) !== '';
    }

    public function verify(?string $token, ?string $remoteIp = null): bool
    {
        $token = trim((string) $token);
        if ($token === '' || !$this->isConfigured()) {
            return false;
        }

        // Google provides fixed test keys. In dev, accept non-empty token directly.
        if ($this->kernelEnvironment === 'dev' && $this->secretKey === self::GOOGLE_TEST_SECRET) {
            return true;
        }

        try {
            $body = http_build_query(array_filter([
                'secret' => $this->secretKey,
                'response' => $token,
                'remoteip' => $remoteIp,
            ], static fn ($value) => $value !== null && $value !== ''));

            $raw = $this->post('https://www.google.com/recaptcha/api/siteverify', $body);
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return false;
        }

        return isset($payload['success']) && $payload['success'] === true;
    }

    private function post(string $url, string $body): string
    {
        $isDev = $this->kernelEnvironment === 'dev';

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_TIMEOUT => 8,
                CURLOPT_SSL_VERIFYPEER => !$isDev,
                CURLOPT_SSL_VERIFYHOST => $isDev ? 0 : 2,
            ]);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!is_string($response) || $response === '' || $error !== '' || $code < 200 || $code >= 300) {
                throw new \RuntimeException('reCAPTCHA request failed');
            }

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => $body,
                'timeout' => 8,
            ],
            'ssl' => [
                'verify_peer' => !$isDev,
                'verify_peer_name' => !$isDev,
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        if (!is_string($response) || $response === '') {
            throw new \RuntimeException('reCAPTCHA request failed');
        }

        return $response;
    }
}
