<?php

namespace App\Service;

class FacePlusPlusCompareService
{
    public function __construct(
        private readonly string $faceppApiKey,
        private readonly string $faceppApiSecret,
        private readonly string $faceppApiBaseUrl,
        private readonly float $faceppMinConfidence,
        private readonly string $kernelEnvironment = 'prod'
    ) {
    }

    public function isConfigured(): bool
    {
        return $this->faceppApiKey !== '' && $this->faceppApiSecret !== '' && $this->faceppApiBaseUrl !== '';
    }

    public function isTransportAvailable(): bool
    {
        return function_exists('curl_init') || in_array('https', stream_get_wrappers(), true);
    }

    public function transportDiagnostic(): string
    {
        $ini = (string) (php_ini_loaded_file() ?: 'none');
        $curlFn = function_exists('curl_init') ? 'yes' : 'no';
        $curlExt = extension_loaded('curl') ? 'yes' : 'no';
        $opensslExt = extension_loaded('openssl') ? 'yes' : 'no';
        $httpsWrapper = in_array('https', stream_get_wrappers(), true) ? 'yes' : 'no';

        return sprintf(
            'sapi=%s; php_ini=%s; curl_fn=%s; curl_ext=%s; openssl_ext=%s; https_wrapper=%s',
            PHP_SAPI,
            $ini,
            $curlFn,
            $curlExt,
            $opensslExt,
            $httpsWrapper
        );
    }

    public function detectFaceTokenFromBase64(string $imageBase64): string
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Face ID provider is not configured.');
        }
        if ($imageBase64 === '') {
            throw new \RuntimeException('Image payload is required.');
        }

        $response = $this->requestJson($this->resolveEndpoint('/facepp/v3/detect'), [
            'api_key' => $this->faceppApiKey,
            'api_secret' => $this->faceppApiSecret,
            'image_base64' => $imageBase64,
        ]);

        $faces = $response['faces'] ?? null;
        if (!is_array($faces) || count($faces) === 0) {
            throw new \RuntimeException('No face detected.');
        }

        $faceToken = (string) ($faces[0]['face_token'] ?? '');
        if ($faceToken === '') {
            throw new \RuntimeException('Face token missing in detect response.');
        }

        return $faceToken;
    }

    public function compareFaceTokens(string $enrolledFaceToken, string $candidateFaceToken): bool
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Face ID provider is not configured.');
        }
        if ($enrolledFaceToken === '' || $candidateFaceToken === '') {
            throw new \RuntimeException('Both Face++ face tokens are required.');
        }

        $response = $this->requestJson($this->resolveEndpoint('/facepp/v3/compare'), [
            'api_key' => $this->faceppApiKey,
            'api_secret' => $this->faceppApiSecret,
            'face_token1' => $enrolledFaceToken,
            'face_token2' => $candidateFaceToken,
        ]);

        $confidence = (float) ($response['confidence'] ?? 0.0);
        return $confidence >= $this->faceppMinConfidence;
    }

    public function providerStatus(): array
    {
        if (!$this->isConfigured()) {
            return [
                'configured' => false,
                'transport_available' => $this->isTransportAvailable(),
                'ok' => false,
                'message' => 'Missing FACEPP configuration',
            ];
        }

        if (!$this->isTransportAvailable()) {
            return [
                'configured' => true,
                'transport_available' => false,
                'ok' => false,
                'message' => 'HTTP transport unavailable',
            ];
        }

        return [
            'configured' => true,
            'transport_available' => true,
            'ok' => true,
            'message' => 'Face ID provider ready',
        ];
    }

    private function resolveEndpoint(string $path): string
    {
        $base = rtrim($this->faceppApiBaseUrl, '/');
        if ($base === '') {
            return '';
        }

        if (str_contains($base, '/facepp/v3')) {
            return $base.(str_starts_with($path, '/facepp/v3') ? substr($path, strlen('/facepp/v3')) : $path);
        }

        return $base.$path;
    }

    private function requestJson(string $url, array $payload): array
    {
        if (!$this->isTransportAvailable()) {
            throw new \RuntimeException('Face ID transport unavailable on web runtime. '.$this->transportDiagnostic());
        }

        $sslVerify = $this->kernelEnvironment !== 'dev';
        $forceInsecureSsl = false;
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;
            $response = null;
            $status = 0;

            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/x-www-form-urlencoded',
                ]);
                curl_setopt($ch, CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);
                $verifyNow = $sslVerify && !$forceInsecureSsl;
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $verifyNow);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $verifyNow ? 2 : 0);

                $response = curl_exec($ch);
                $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                if ($response === false) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    $isCertError = str_contains(strtolower($error), 'unable to get local issuer certificate')
                        || str_contains(strtolower($error), 'ssl certificate problem');
                    if ($isCertError && !$forceInsecureSsl) {
                        $forceInsecureSsl = true;
                        if ($attempt < $maxAttempts) {
                            continue;
                        }
                    }
                    if ($attempt < $maxAttempts && str_contains(strtolower($error), 'timed out')) {
                        usleep((int) (200000 * $attempt));
                        continue;
                    }
                    throw new \RuntimeException('Face ID provider is unavailable (network/SSL).');
                }
                curl_close($ch);
            } else {
                $body = http_build_query($payload);
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                        'content' => $body,
                        'timeout' => 20,
                        'ignore_errors' => true,
                    ],
                    'ssl' => [
                        'verify_peer' => $sslVerify,
                        'verify_peer_name' => $sslVerify,
                    ],
                ]);

                $response = @file_get_contents($url, false, $context);
                if ($response === false) {
                    if ($attempt < $maxAttempts) {
                        usleep((int) (200000 * $attempt));
                        continue;
                    }
                    throw new \RuntimeException('Face ID provider is unavailable (network/SSL).');
                }

                $httpResponseHeader = $http_response_header ?? [];
                foreach ($httpResponseHeader as $line) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                        $status = (int) $m[1];
                        break;
                    }
                }
            }

            $data = json_decode((string) $response, true);
            if (!is_array($data)) {
                throw new \RuntimeException('Invalid Face ID provider response.');
            }
            if (isset($data['error_message'])) {
                $message = (string) $data['error_message'];
                if (str_contains($message, 'CONCURRENCY_LIMIT_EXCEEDED') && $attempt < $maxAttempts) {
                    usleep((int) (250000 * $attempt));
                    continue;
                }
                throw new \RuntimeException('Face ID provider error: '.$message);
            }
            if ($status >= 400) {
                if ($attempt < $maxAttempts && ($status === 429 || $status >= 500)) {
                    usleep((int) (250000 * $attempt));
                    continue;
                }
                throw new \RuntimeException('Face ID provider HTTP error '.$status.'.');
            }

            return $data;
        }

        throw new \RuntimeException('Face ID provider is unavailable. Please try again.');
    }
}
