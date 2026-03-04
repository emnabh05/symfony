<?php

namespace App\Service;

class FoodRecognitionService
{
    private string $calorieNinjasKey;
    private string $huggingfaceKey;

    public function __construct(
        string $calorieNinjasKey,
        string $huggingfaceKey
    ) {
        $this->calorieNinjasKey = $calorieNinjasKey;
        $this->huggingfaceKey = $huggingfaceKey;
    }

    public function analyzeImage(string $imagePath): array
    {
        if ($this->huggingfaceKey === '') {
            throw new \RuntimeException('Hugging Face API key not configured.');
        }

        $imageBinary = (string) file_get_contents($imagePath);
        $mime = mime_content_type($imagePath) ?: 'application/octet-stream';
        $headers = [
            'Authorization: Bearer '.$this->huggingfaceKey,
            'Content-Type: '.$mime,
            'Accept: application/json',
        ];

        try {
            $hfResponse = $this->requestJson(
                'https://router.huggingface.co/hf-inference/models/nateraw/food',
                $headers,
                $imageBinary
            );
        } catch (\RuntimeException $e) {
            // Fallback to the standard inference endpoint if the router fails.
            $hfResponse = $this->requestJson(
                'https://api-inference.huggingface.co/models/nateraw/food',
                $headers,
                $imageBinary
            );
        }

        if (!is_array($hfResponse) || count($hfResponse) === 0) {
            throw new \RuntimeException('No food detected.');
        }
        $top = $hfResponse[0];
        $foodName = (string) ($top['label'] ?? '');
        $confidence = (float) ($top['score'] ?? 0);
        if ($foodName === '') {
            throw new \RuntimeException('No food name detected.');
        }

        $item = null;
        $nutritionSource = 'estimate';
        if ($this->calorieNinjasKey !== '') {
            try {
                $calorieResponse = $this->requestJson(
                    'https://api.calorieninjas.com/v1/nutrition?query='.rawurlencode($foodName),
                    [
                        'X-Api-Key: '.$this->calorieNinjasKey,
                    ]
                );
                $item = $calorieResponse['items'][0] ?? null;
                if ($item) {
                    $nutritionSource = 'calorieninjas';
                }
            } catch (\Throwable) {
                // Ignore provider failure and use local estimation fallback.
                $item = null;
            }
        }

        if (!$item) {
            $item = $this->estimateNutritionForFood($foodName);
        }

        return [
            'food' => $foodName,
            'confidence' => round($confidence * 100, 1),
            'calories' => (float) ($item['calories'] ?? 0),
            'protein' => (float) ($item['protein_g'] ?? 0),
            'carbs' => (float) ($item['carbohydrates_total_g'] ?? 0),
            'fat' => (float) ($item['fat_total_g'] ?? 0),
            'nutrition_source' => $nutritionSource,
        ];
    }

    private function estimateNutritionForFood(string $foodName): array
    {
        $name = mb_strtolower(trim($foodName));

        $catalog = [
            'banana' => ['calories' => 89, 'protein_g' => 1.1, 'carbohydrates_total_g' => 22.8, 'fat_total_g' => 0.3],
            'apple' => ['calories' => 52, 'protein_g' => 0.3, 'carbohydrates_total_g' => 13.8, 'fat_total_g' => 0.2],
            'orange' => ['calories' => 47, 'protein_g' => 0.9, 'carbohydrates_total_g' => 11.8, 'fat_total_g' => 0.1],
            'egg' => ['calories' => 155, 'protein_g' => 13.0, 'carbohydrates_total_g' => 1.1, 'fat_total_g' => 11.0],
            'chicken' => ['calories' => 239, 'protein_g' => 27.0, 'carbohydrates_total_g' => 0.0, 'fat_total_g' => 14.0],
            'beef' => ['calories' => 250, 'protein_g' => 26.0, 'carbohydrates_total_g' => 0.0, 'fat_total_g' => 15.0],
            'fish' => ['calories' => 206, 'protein_g' => 22.0, 'carbohydrates_total_g' => 0.0, 'fat_total_g' => 12.0],
            'rice' => ['calories' => 130, 'protein_g' => 2.7, 'carbohydrates_total_g' => 28.0, 'fat_total_g' => 0.3],
            'pasta' => ['calories' => 131, 'protein_g' => 5.0, 'carbohydrates_total_g' => 25.0, 'fat_total_g' => 1.1],
            'bread' => ['calories' => 265, 'protein_g' => 9.0, 'carbohydrates_total_g' => 49.0, 'fat_total_g' => 3.2],
            'pizza' => ['calories' => 266, 'protein_g' => 11.0, 'carbohydrates_total_g' => 33.0, 'fat_total_g' => 10.0],
            'burger' => ['calories' => 295, 'protein_g' => 17.0, 'carbohydrates_total_g' => 30.0, 'fat_total_g' => 13.0],
            'salad' => ['calories' => 80, 'protein_g' => 2.0, 'carbohydrates_total_g' => 8.0, 'fat_total_g' => 4.0],
            'potato' => ['calories' => 77, 'protein_g' => 2.0, 'carbohydrates_total_g' => 17.0, 'fat_total_g' => 0.1],
            'fries' => ['calories' => 312, 'protein_g' => 3.4, 'carbohydrates_total_g' => 41.0, 'fat_total_g' => 15.0],
            'yogurt' => ['calories' => 61, 'protein_g' => 3.5, 'carbohydrates_total_g' => 4.7, 'fat_total_g' => 3.3],
            'cheese' => ['calories' => 402, 'protein_g' => 25.0, 'carbohydrates_total_g' => 1.3, 'fat_total_g' => 33.0],
            'cake' => ['calories' => 350, 'protein_g' => 4.0, 'carbohydrates_total_g' => 50.0, 'fat_total_g' => 15.0],
            'sandwich' => ['calories' => 250, 'protein_g' => 12.0, 'carbohydrates_total_g' => 28.0, 'fat_total_g' => 9.0],
        ];

        foreach ($catalog as $keyword => $nutrition) {
            if (str_contains($name, $keyword)) {
                return $nutrition;
            }
        }

        return [
            'calories' => 180,
            'protein_g' => 8.0,
            'carbohydrates_total_g' => 18.0,
            'fat_total_g' => 8.0,
        ];
    }

    private function requestJson(string $url, array $headers, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($response === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('HTTP request failed: '.$err);
        }
        curl_close($ch);
        $data = json_decode($response, true);
        if (!is_array($data)) {
            if ($status >= 400) {
                $snippet = trim(preg_replace('/\s+/', ' ', strip_tags((string) $response)));
                $snippet = mb_substr($snippet, 0, 200);
                $msg = $snippet !== '' ? $snippet : 'API error';
                throw new \RuntimeException($msg.' (HTTP '.$status.')');
            }
            throw new \RuntimeException('Invalid JSON response (HTTP '.$status.').');
        }
        if ($status >= 400) {
            $msg = $data['status']['description'] ?? $data['error']['message'] ?? $data['error'] ?? 'API error';
            throw new \RuntimeException($msg.' (HTTP '.$status.')');
        }
        return $data;
    }
}
