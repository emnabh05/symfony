<?php

namespace App\Service;

class ExploreSummaryService
{
    public function __construct(
        private string $geminiKey = '',
        private string $geminiModel = 'gemini-2.0-flash'
    ) {
    }

    public function summarize(string $title, string $content): string
    {
        $key = trim($this->geminiKey);
        if ($key === '') {
            throw new \RuntimeException('Gemini API key not configured. Set GEMINI_API_KEY in .env.local.');
        }

        $cleanTitle = $this->cleanText($title);
        $cleanContent = $this->cleanText($content);

        if ($cleanContent === '') {
            throw new \RuntimeException('Post content is empty.');
        }

        $input = $cleanTitle !== ''
            ? ("Title: " . $cleanTitle . "\nContent: " . $cleanContent)
            : $cleanContent;

        $model = trim($this->geminiModel) !== '' ? trim($this->geminiModel) : 'gemini-2.0-flash';
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($key)
        );

        $prompt = implode("\n", [
            'Summarize the post in 2-4 friendly sentences.',
            'Keep it concise, neutral, and helpful.',
            'Do not add new facts. No markdown. Output plain text only.',
            '',
            $input,
        ]);

        $response = $this->requestJson(
            $endpoint,
            ['Content-Type: application/json'],
            json_encode([
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'maxOutputTokens' => 220,
                    'responseMimeType' => 'text/plain',
                ],
            ], JSON_UNESCAPED_SLASHES),
            12
        );

        $text = $this->extractGeminiText($response);
        $text = trim($text);
        if ($text === '') {
            throw new \RuntimeException('Empty AI summary response.');
        }

        return $text;
    }

    /**
     * @return array{summary: string, source: string}
     */
    public function summarizeWithFallback(string $title, string $content, ?string $excerpt = null): array
    {
        try {
            return [
                'summary' => $this->summarize($title, $content),
                'source' => 'ai',
            ];
        } catch (\Throwable $e) {
            $fallback = $this->fallbackSummary($excerpt ?? '', $content);
            return [
                'summary' => $fallback,
                'source' => 'fallback',
            ];
        }
    }

    private function cleanText(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = trim($text);
        if (mb_strlen($text) > 6000) {
            $text = mb_substr($text, 0, 6000);
        }
        return $text;
    }

    private function fallbackSummary(string $excerpt, string $content): string
    {
        $base = $this->cleanText($content);
        if ($base === '') {
            $base = $this->cleanText($excerpt);
        }

        if ($base === '') {
            return 'Summary not available for this post yet.';
        }

        $sentences = preg_split('/(?<=[.!?])\s+/', $base) ?: [];
        $picked = [];
        foreach ($sentences as $sentence) {
            $s = trim($sentence);
            if ($s === '') {
                continue;
            }
            $picked[] = $s;
            if (count($picked) >= 3) {
                break;
            }
        }

        if (count($picked) > 0) {
            return implode(' ', $picked);
        }

        $words = preg_split('/\s+/', $base) ?: [];
        $slice = array_slice($words, 0, 60);
        return trim(implode(' ', $slice));
    }

    private function extractGeminiText(array $response): string
    {
        if (
            isset($response['candidates'][0]['content']['parts'][0]['text']) &&
            is_string($response['candidates'][0]['content']['parts'][0]['text'])
        ) {
            return $response['candidates'][0]['content']['parts'][0]['text'];
        }

        if (
            isset($response['candidates'][0]['output']) &&
            is_string($response['candidates'][0]['output'])
        ) {
            return $response['candidates'][0]['output'];
        }

        return '';
    }

    private function requestJson(string $url, array $headers, ?string $body = null, int $timeoutSeconds = 12): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, max(4, $timeoutSeconds));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: ' . $error);
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid AI response (HTTP ' . $status . ').');
        }

        if ($status >= 400) {
            $msg = $data['error']['message'] ?? $data['error'] ?? $data['message'] ?? 'AI API error';
            throw new \RuntimeException((string) $msg . ' (HTTP ' . $status . ')');
        }

        return $data;
    }
}
