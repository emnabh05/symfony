<?php

namespace App\Service;

use App\Entity\Supplement;

class AiProductSuggestionService
{
    public function __construct(
        private string $huggingfaceKey,
        private string $geminiKey = '',
        private string $geminiModel = 'gemini-2.0-flash',
    ) {
    }

    /**
     * @param Supplement[] $catalog
     * @param int[] $cartProductIds
     * @return array<int, array{supplement: Supplement, reason: string, score: float, source: string}>
     */
    public function suggest(array $catalog, ?Supplement $focusProduct = null, array $cartProductIds = [], string $query = '', int $limit = 6): array
    {
        $limit = max(1, min(10, $limit));
        $catalog = array_values(array_filter($catalog, static fn ($item): bool => $item instanceof Supplement && ($item->getStock() ?? 0) > 0));
        $cartProductIds = array_values(array_unique(array_map(static fn ($id): int => (int) $id, $cartProductIds)));

        if (count($catalog) === 0) {
            return [];
        }

        $heuristic = $this->buildHeuristicSuggestions($catalog, $focusProduct, $cartProductIds, $query, $limit);

        if (trim($this->geminiKey) !== '') {
            try {
                $geminiSuggestions = $this->buildGeminiSuggestions($catalog, $focusProduct, $cartProductIds, $query, $limit);
                if (count($geminiSuggestions) >= min(2, $limit)) {
                    return $geminiSuggestions;
                }
            } catch (\Throwable $e) {
                error_log('Gemini suggestions fallback triggered: ' . $e->getMessage());
            }
        }

        if (trim($this->huggingfaceKey) !== '') {
            try {
                $aiSuggestions = $this->buildAiSuggestions($catalog, $focusProduct, $cartProductIds, $query, $limit);
                if (count($aiSuggestions) >= min(2, $limit)) {
                    return $aiSuggestions;
                }
            } catch (\Throwable $e) {
                error_log('AI suggestions fallback triggered: ' . $e->getMessage());
            }
        }

        return $heuristic;
    }

    /**
     * @param Supplement[] $catalog
     * @param int[] $cartProductIds
     * @return array<int, array{supplement: Supplement, reason: string, score: float, source: string}>
     */
    private function buildHeuristicSuggestions(array $catalog, ?Supplement $focusProduct, array $cartProductIds, string $query, int $limit): array
    {
        $cartLookup = [];
        foreach ($cartProductIds as $id) {
            $cartLookup[$id] = true;
        }

        $contextTokens = $this->tokenize($query);
        $focusCategory = '';
        $focusBrand = '';
        $focusPrice = null;
        if ($focusProduct !== null) {
            $contextTokens = array_values(array_unique(array_merge(
                $contextTokens,
                $this->tokenize((string) ($focusProduct->getName() . ' ' . $focusProduct->getDescription()))
            )));
            $focusCategory = strtolower((string) $focusProduct->getCategory());
            $focusBrand = strtolower((string) $focusProduct->getBrand());
            $focusPrice = (float) $focusProduct->getPrice();
        }

        $scored = [];
        foreach ($catalog as $candidate) {
            $candidateId = $candidate->getId();
            if ($candidateId === null) {
                continue;
            }

            if (isset($cartLookup[$candidateId])) {
                continue;
            }

            if ($focusProduct !== null && $focusProduct->getId() === $candidateId) {
                continue;
            }

            $score = 0.0;
            $reasons = [];
            $candidateCategory = strtolower((string) $candidate->getCategory());
            $candidateBrand = strtolower((string) $candidate->getBrand());

            if ($focusCategory !== '' && $candidateCategory === $focusCategory) {
                $score += 35;
                $reasons[] = 'same category as viewed product';
            }

            if ($focusBrand !== '' && $candidateBrand === $focusBrand) {
                $score += 15;
                $reasons[] = 'same brand preference';
            }

            if ($focusPrice !== null && $focusPrice > 0) {
                $deltaRatio = abs(((float) $candidate->getPrice()) - $focusPrice) / max(0.01, $focusPrice);
                if ($deltaRatio <= 0.25) {
                    $score += 18;
                    $reasons[] = 'price close to what you viewed';
                } elseif ($deltaRatio <= 0.45) {
                    $score += 8;
                }
            }

            $textTokens = $this->tokenize((string) ($candidate->getName() . ' ' . $candidate->getDescription() . ' ' . $candidate->getCategory() . ' ' . $candidate->getBrand()));
            $queryMatch = $this->jaccardSimilarity($contextTokens, $textTokens);
            if ($queryMatch > 0) {
                $score += $queryMatch * 30;
                if ($queryMatch >= 0.2) {
                    $reasons[] = 'matches your search intent';
                }
            }

            $stockBoost = min(1.0, ($candidate->getStock() ?? 0) / 30);
            $score += $stockBoost * 12;
            if (($candidate->getStock() ?? 0) >= 10) {
                $reasons[] = 'high stock availability';
            }

            // Add slight score jitter to prevent repetitive fixed ordering.
            $score += mt_rand(0, 250) / 100;

            if (count($reasons) === 0) {
                $reasons[] = 'good overall match';
            }

            $scored[] = [
                'supplement' => $candidate,
                'reason' => $this->firstReason($reasons),
                'score' => round($score, 2),
                'source' => 'heuristic',
            ];
        }

        usort($scored, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return $this->diversifyRankedSuggestions($scored, $limit);
    }

    /**
     * @param Supplement[] $catalog
     * @param int[] $cartProductIds
     * @return array<int, array{supplement: Supplement, reason: string, score: float, source: string}>
     */
    private function buildGeminiSuggestions(array $catalog, ?Supplement $focusProduct, array $cartProductIds, string $query, int $limit): array
    {
        $catalogChunk = array_slice($catalog, 0, 60);
        $catalogRows = [];
        foreach ($catalogChunk as $supplement) {
            $catalogRows[] = sprintf(
                'ID=%d | Name=%s | Category=%s | Brand=%s | Price=%s | Stock=%d',
                (int) $supplement->getId(),
                $this->safeInline((string) $supplement->getName()),
                $this->safeInline((string) $supplement->getCategory()),
                $this->safeInline((string) $supplement->getBrand()),
                (string) $supplement->getPrice(),
                (int) $supplement->getStock()
            );
        }

        $focusPart = 'none';
        if ($focusProduct !== null && $focusProduct->getId() !== null) {
            $focusPart = sprintf(
                'ID=%d, Name=%s, Category=%s, Brand=%s',
                (int) $focusProduct->getId(),
                $this->safeInline((string) $focusProduct->getName()),
                $this->safeInline((string) $focusProduct->getCategory()),
                $this->safeInline((string) $focusProduct->getBrand())
            );
        }

        $prompt = implode("\n", [
            'You are a recommendation engine for sports nutrition products.',
            'Return ONLY valid JSON and nothing else.',
            'JSON format: [{"id": number, "reason": "short reason", "confidence": 0.0-1.0}]',
            sprintf('Return exactly %d products.', $limit),
            'Prioritize variety across categories and brands.',
            'User query: ' . ($query !== '' ? $this->safeInline($query) : 'none'),
            'Focused product: ' . $focusPart,
            'Cart product IDs: ' . (count($cartProductIds) > 0 ? implode(',', $cartProductIds) : 'none'),
            'Catalog:',
            implode("\n", $catalogRows),
        ]);

        $model = trim($this->geminiModel) !== '' ? trim($this->geminiModel) : 'gemini-2.0-flash';
        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent?key=%s',
            rawurlencode($model),
            rawurlencode($this->geminiKey)
        );

        $response = $this->requestJson(
            $endpoint,
            ['Content-Type: application/json'],
            json_encode([
                'contents' => [[
                    'role' => 'user',
                    'parts' => [['text' => $prompt]],
                ]],
                'generationConfig' => [
                    'temperature' => 0.45,
                    'maxOutputTokens' => 420,
                    'responseMimeType' => 'application/json',
                ],
            ], JSON_UNESCAPED_SLASHES),
            10
        );

        $generatedText = $this->extractGeminiGeneratedText($response);
        $decoded = $this->decodeGeneratedJsonArray($generatedText);
        if (count($decoded) === 0) {
            return [];
        }

        $catalogById = [];
        foreach ($catalog as $supplement) {
            if ($supplement->getId() !== null) {
                $catalogById[(int) $supplement->getId()] = $supplement;
            }
        }

        $result = [];
        $seen = [];
        foreach ($decoded as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0 || isset($seen[$id]) || !isset($catalogById[$id])) {
                continue;
            }

            $reason = trim((string) ($item['reason'] ?? 'AI recommended this product for your fitness goal.'));
            if ($reason === '') {
                $reason = 'AI recommended this product for your fitness goal.';
            }

            $confidence = isset($item['confidence']) ? (float) $item['confidence'] : 0.68;
            if ($confidence > 1.0 && $confidence <= 100.0) {
                $confidence /= 100.0;
            }

            $result[] = [
                'supplement' => $catalogById[$id],
                'reason' => substr($reason, 0, 160),
                'score' => round(max(0, min(1, $confidence)) * 100, 2),
                'source' => 'ai',
            ];
            $seen[$id] = true;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param Supplement[] $catalog
     * @param int[] $cartProductIds
     * @return array<int, array{supplement: Supplement, reason: string, score: float, source: string}>
     */
    private function buildAiSuggestions(array $catalog, ?Supplement $focusProduct, array $cartProductIds, string $query, int $limit): array
    {
        $catalogChunk = array_slice($catalog, 0, 40);
        $catalogRows = [];
        foreach ($catalogChunk as $supplement) {
            $catalogRows[] = sprintf(
                'ID=%d | Name=%s | Category=%s | Brand=%s | Price=%s | Stock=%d',
                (int) $supplement->getId(),
                $this->safeInline((string) $supplement->getName()),
                $this->safeInline((string) $supplement->getCategory()),
                $this->safeInline((string) $supplement->getBrand()),
                (string) $supplement->getPrice(),
                (int) $supplement->getStock()
            );
        }

        $focusPart = 'none';
        if ($focusProduct !== null && $focusProduct->getId() !== null) {
            $focusPart = sprintf(
                'ID=%d, Name=%s, Category=%s, Brand=%s',
                (int) $focusProduct->getId(),
                $this->safeInline((string) $focusProduct->getName()),
                $this->safeInline((string) $focusProduct->getCategory()),
                $this->safeInline((string) $focusProduct->getBrand())
            );
        }

        $prompt = implode("\n", [
            'You are a recommendation engine for sports nutrition products.',
            'Return ONLY a strict JSON array, no markdown, no explanation.',
            'Each element must be: {"id": number, "reason": "short reason", "confidence": 0.0-1.0}',
            sprintf('Return exactly %d items.', $limit),
            'Prefer variety across brands and categories.',
            'User query: ' . ($query !== '' ? $this->safeInline($query) : 'none'),
            'Focused product: ' . $focusPart,
            'Cart product IDs: ' . (count($cartProductIds) > 0 ? implode(',', $cartProductIds) : 'none'),
            'Diversity seed: ' . (string) mt_rand(1000, 999999),
            'Catalog:',
            implode("\n", $catalogRows),
        ]);

        $response = $this->requestJson(
            'https://router.huggingface.co/hf-inference/models/google/flan-t5-base',
            [
                'Authorization: Bearer ' . $this->huggingfaceKey,
                'Content-Type: application/json',
            ],
            json_encode([
                'inputs' => $prompt,
                'parameters' => [
                    'max_new_tokens' => 320,
                    'temperature' => 0.45,
                    'return_full_text' => false,
                ],
            ], JSON_UNESCAPED_SLASHES)
        );

        $generatedText = $this->extractGeneratedText($response);
        $decoded = $this->decodeGeneratedJsonArray($generatedText);
        if (count($decoded) === 0) {
            return [];
        }

        $catalogById = [];
        foreach ($catalog as $supplement) {
            if ($supplement->getId() !== null) {
                $catalogById[(int) $supplement->getId()] = $supplement;
            }
        }

        $result = [];
        $seen = [];
        foreach ($decoded as $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if ($id <= 0 || isset($seen[$id]) || !isset($catalogById[$id])) {
                continue;
            }

            $reason = trim((string) ($item['reason'] ?? 'AI recommended this product for your context.'));
            if ($reason === '') {
                $reason = 'AI recommended this product for your context.';
            }

            $confidence = isset($item['confidence']) ? (float) $item['confidence'] : 0.65;
            $result[] = [
                'supplement' => $catalogById[$id],
                'reason' => substr($reason, 0, 160),
                'score' => round(max(0, min(1, $confidence)) * 100, 2),
                'source' => 'ai',
            ];
            $seen[$id] = true;

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @param array<int, array{supplement: Supplement, reason: string, score: float, source: string}> $ranked
     * @return array<int, array{supplement: Supplement, reason: string, score: float, source: string}>
     */
    private function diversifyRankedSuggestions(array $ranked, int $limit): array
    {
        if (count($ranked) <= $limit) {
            return array_slice($ranked, 0, $limit);
        }

        $poolSize = min(count($ranked), max($limit * 5, $limit + 4));
        $pool = array_slice($ranked, 0, $poolSize);
        $selected = [];
        $selectedIds = [];
        $categoryCounts = [];
        $brandCounts = [];

        foreach ($pool as $item) {
            $supplement = $item['supplement'];
            $supplementId = (int) ($supplement->getId() ?? 0);
            if ($supplementId <= 0 || isset($selectedIds[$supplementId])) {
                continue;
            }

            $category = strtolower(trim((string) $supplement->getCategory()));
            $brand = strtolower(trim((string) $supplement->getBrand()));
            $categoryLimitReached = ($category !== '' && ($categoryCounts[$category] ?? 0) >= 2);
            $brandLimitReached = ($brand !== '' && ($brandCounts[$brand] ?? 0) >= 2);

            if ($categoryLimitReached || $brandLimitReached) {
                continue;
            }

            $selected[] = $item;
            $selectedIds[$supplementId] = true;
            if ($category !== '') {
                $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
            }
            if ($brand !== '') {
                $brandCounts[$brand] = ($brandCounts[$brand] ?? 0) + 1;
            }

            if (count($selected) >= $limit) {
                break;
            }
        }

        if (count($selected) < $limit) {
            foreach ($pool as $item) {
                $supplement = $item['supplement'];
                $supplementId = (int) ($supplement->getId() ?? 0);
                if ($supplementId <= 0 || isset($selectedIds[$supplementId])) {
                    continue;
                }
                $selected[] = $item;
                $selectedIds[$supplementId] = true;
                if (count($selected) >= $limit) {
                    break;
                }
            }
        }

        return array_slice($selected, 0, $limit);
    }

    private function extractGeminiGeneratedText(array $response): string
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

    private function extractGeneratedText(array $response): string
    {
        if (isset($response[0]['generated_text']) && is_string($response[0]['generated_text'])) {
            return $response[0]['generated_text'];
        }

        if (isset($response['generated_text']) && is_string($response['generated_text'])) {
            return $response['generated_text'];
        }

        return '';
    }

    /**
     * @return array<int, array{id?: int, reason?: string, confidence?: float}>
     */
    private function decodeGeneratedJsonArray(string $generatedText): array
    {
        $generatedText = trim($generatedText);
        if ($generatedText === '') {
            return [];
        }

        $decoded = json_decode($generatedText, true);
        if (is_array($decoded)) {
            if (array_is_list($decoded)) {
                return $decoded;
            }

            foreach (['suggestions', 'items', 'recommendations', 'products'] as $key) {
                if (isset($decoded[$key]) && is_array($decoded[$key]) && array_is_list($decoded[$key])) {
                    return $decoded[$key];
                }
            }
        }

        if (preg_match('/\[[\s\S]*\]/', $generatedText, $matches) === 1) {
            $decodedBlock = json_decode($matches[0], true);
            if (is_array($decodedBlock) && array_is_list($decodedBlock)) {
                return $decodedBlock;
            }
        }

        return [];
    }

    private function safeInline(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? '';
        return str_replace(['|', '"'], ['/', "'"], $value);
    }

    /**
     * @param string[] $left
     * @param string[] $right
     */
    private function jaccardSimilarity(array $left, array $right): float
    {
        if (count($left) === 0 || count($right) === 0) {
            return 0.0;
        }

        $intersection = array_intersect($left, $right);
        $union = array_unique(array_merge($left, $right));
        if (count($union) === 0) {
            return 0.0;
        }

        return count($intersection) / count($union);
    }

    /**
     * @return string[]
     */
    private function tokenize(string $text): array
    {
        $text = strtolower(strip_tags($text));
        $tokens = preg_split('/[^a-z0-9]+/', $text) ?: [];
        $tokens = array_filter($tokens, static fn (string $token): bool => strlen($token) >= 3);

        return array_values(array_unique($tokens));
    }

    /**
     * @param string[] $reasons
     */
    private function firstReason(array $reasons): string
    {
        return $reasons[0] ?? 'good overall match';
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
