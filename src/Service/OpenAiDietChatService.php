<?php

namespace App\Service;

class OpenAiDietChatService
{
    public function reply(string $message, array $context = []): string
    {
        $message = trim($message);
        if ($message === '') {
            throw new \RuntimeException('Message is empty.');
        }

        $firstName = trim((string) ($context['first_name'] ?? ''));
        $systemPrompt = 'You are a helpful nutrition assistant. Give concise, practical advice. '
            .'Do not diagnose diseases. If the request is medical, recommend consulting a professional.';

        if ($firstName !== '') {
            $systemPrompt .= ' User first name: '.$firstName.'.';
        }

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $message],
        ];
        $providerErrors = [];

        $groqKey = trim((string) ($_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY'] ?? ''));
        if ($groqKey !== '') {
            try {
                $data = $this->requestJson(
                    'https://api.groq.com/openai/v1/chat/completions',
                    [
                        'Authorization: Bearer '.$groqKey,
                        'Content-Type: application/json',
                    ],
                    [
                        'model' => 'llama-3.1-8b-instant',
                        'messages' => $messages,
                        'temperature' => 0.5,
                        'max_tokens' => 400,
                    ]
                );

                $content = (string) ($data['choices'][0]['message']['content'] ?? '');
                if ($content === '') {
                    throw new \RuntimeException('AI service returned an empty response.');
                }

                return trim($content);
            } catch (\Throwable $exception) {
                $providerErrors[] = 'groq: '.$exception->getMessage();
            }
        }

        $openAiKey = trim((string) ($_ENV['OPENAI_API_KEY'] ?? $_SERVER['OPENAI_API_KEY'] ?? ''));
        if ($openAiKey !== '') {
            try {
                $data = $this->requestJson(
                    'https://api.openai.com/v1/chat/completions',
                    [
                        'Authorization: Bearer '.$openAiKey,
                        'Content-Type: application/json',
                    ],
                    [
                        'model' => 'gpt-4o-mini',
                        'messages' => $messages,
                        'temperature' => 0.5,
                        'max_tokens' => 400,
                    ]
                );

                $content = (string) ($data['choices'][0]['message']['content'] ?? '');
                if ($content === '') {
                    throw new \RuntimeException('AI service returned an empty response.');
                }

                return trim($content);
            } catch (\Throwable $exception) {
                $providerErrors[] = 'openai: '.$exception->getMessage();
            }
        }

        $geminiKey = trim((string) ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? ''));
        if ($geminiKey !== '') {
            $geminiModel = trim((string) ($_ENV['GEMINI_MODEL'] ?? $_SERVER['GEMINI_MODEL'] ?? 'gemini-2.0-flash'));
            if ($geminiModel === '') {
                $geminiModel = 'gemini-2.0-flash';
            }

            try {
                $data = $this->requestJson(
                    'https://generativelanguage.googleapis.com/v1beta/models/'
                    .rawurlencode($geminiModel)
                    .':generateContent?key='
                    .rawurlencode($geminiKey),
                    [
                        'Content-Type: application/json',
                    ],
                    [
                        'system_instruction' => [
                            'parts' => [
                                ['text' => $systemPrompt],
                            ],
                        ],
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $message],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => 0.5,
                            'maxOutputTokens' => 400,
                        ],
                    ]
                );

                $parts = $data['candidates'][0]['content']['parts'] ?? [];
                if (!is_array($parts)) {
                    $parts = [];
                }

                $content = '';
                foreach ($parts as $part) {
                    $text = (string) ($part['text'] ?? '');
                    if ($text !== '') {
                        $content .= ($content === '' ? '' : "\n").$text;
                    }
                }

                if ($content === '') {
                    throw new \RuntimeException('AI service returned an empty response.');
                }

                return trim($content);
            } catch (\Throwable $exception) {
                $providerErrors[] = 'gemini: '.$exception->getMessage();
            }
        }

        if (!empty($providerErrors)) {
            return $this->buildLocalFallbackAdvice($message, $firstName);
        }

        throw new \RuntimeException('Missing API key. Set GROQ_API_KEY, OPENAI_API_KEY, or GEMINI_API_KEY in .env.local.');
    }

    private function buildLocalFallbackAdvice(string $message, string $firstName = ''): string
    {
        $namePrefix = $firstName !== '' ? $firstName.', ' : '';
        $base = $namePrefix.'AI service is temporarily unavailable. Here is a quick nutrition fallback plan:';

        $tips = [
            'Build each meal with: 1 protein source, 1 fiber-rich carb, vegetables, and water.',
            'Target 3 main meals and 1 healthy snack today to avoid energy crashes.',
            'Keep ultra-processed foods low and prioritize whole foods.',
            'If your goal is fat loss: reduce portions slightly and increase daily steps.',
            'If your goal is muscle gain: add one extra protein snack and stay hydrated.',
        ];

        $lowMessage = mb_strtolower($message);
        if (str_contains($lowMessage, 'weight') || str_contains($lowMessage, 'poids') || str_contains($lowMessage, 'fat')) {
            $tips[] = 'For weight control: aim for consistent meal timing and avoid liquid calories.';
        }
        if (str_contains($lowMessage, 'muscle') || str_contains($lowMessage, 'masse') || str_contains($lowMessage, 'protein')) {
            $tips[] = 'For muscle focus: spread protein across meals (around 25-35g per meal).';
        }

        return $base."\n- ".implode("\n- ", array_slice($tips, 0, 5));
    }

    private function requestJson(string $url, array $headers, array $payload): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('AI request failed: '.$error);
        }

        curl_close($ch);

        $data = json_decode($response, true);
        if (!is_array($data)) {
            throw new \RuntimeException('Invalid AI response (HTTP '.$status.').');
        }

        if ($status >= 400) {
            $msg = $data['error']['message'] ?? 'AI service error';
            throw new \RuntimeException($msg.' (HTTP '.$status.')');
        }

        return $data;
    }
}
