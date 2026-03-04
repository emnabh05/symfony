<?php

namespace App\Service;

class TwilioSmsService
{
    private string $accountSid;
    private string $authToken;
    private string $fromNumber;

    public function __construct(string $accountSid, string $authToken, string $fromNumber)
    {
        $this->accountSid = trim($accountSid);
        $this->authToken = trim($authToken);
        $this->fromNumber = trim($fromNumber);
    }

    public function isConfigured(): bool
    {
        return $this->accountSid !== '' && $this->authToken !== '' && $this->fromNumber !== '';
    }

    /**
     * @return array{sid:string,status:string}
     */
    public function sendMessage(string $toNumber, string $message): array
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('Twilio SMS is not configured.');
        }

        $to = $this->normalizePhoneNumber($toNumber);
        if ($to === null) {
            throw new \RuntimeException('Invalid destination phone number.');
        }

        $body = trim($message);
        if ($body === '') {
            throw new \RuntimeException('SMS body is empty.');
        }

        if (!function_exists('curl_init')) {
            throw new \RuntimeException('cURL extension is required for Twilio SMS.');
        }

        $endpoint = sprintf(
            'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json',
            rawurlencode($this->accountSid)
        );

        $payload = http_build_query([
            'From' => $this->fromNumber,
            'To' => $to,
            'Body' => $body,
        ]);

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_USERPWD, $this->accountSid.':'.$this->authToken);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('Twilio HTTP request failed: '.$error);
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid Twilio response.');
        }

        if ($status >= 400) {
            $errorMessage = (string) ($data['message'] ?? $data['detail'] ?? 'Twilio SMS API error.');
            $errorCode = isset($data['code']) ? ' #'.$data['code'] : '';
            throw new \RuntimeException($errorMessage.$errorCode.' (HTTP '.$status.')');
        }

        return [
            'sid' => (string) ($data['sid'] ?? ''),
            'status' => (string) ($data['status'] ?? 'queued'),
        ];
    }

    private function normalizePhoneNumber(string $value): ?string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        $normalized = preg_replace('/(?!^\+)[^\d]/', '', $trimmed);
        if (!is_string($normalized) || $normalized === '') {
            return null;
        }

        if ($normalized[0] !== '+') {
            $normalized = '+'.$normalized;
        }

        if (!preg_match('/^\+\d{8,15}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}

