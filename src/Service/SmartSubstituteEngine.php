<?php

namespace App\Service;

use App\Entity\Supplement;
use App\Repository\SupplementRepository;

class SmartSubstituteEngine
{
    public function __construct(
        private SupplementRepository $supplementRepository,
    ) {
    }

    /**
     * @return array<int, array{supplement: Supplement, score: float, reasons: string[]}>
     */
    public function suggestForSupplement(Supplement $target, int $requestedQuantity = 1, int $limit = 5): array
    {
        $requestedQuantity = max(1, $requestedQuantity);
        $limit = max(1, min(12, $limit));

        $targetId = $target->getId();
        if ($targetId === null) {
            return [];
        }

        $candidates = $this->supplementRepository->findInStockAlternatives($targetId, $requestedQuantity, 120);
        if (count($candidates) === 0) {
            return [];
        }

        $scored = [];
        foreach ($candidates as $candidate) {
            $score = 0.0;
            $reasons = [];

            $targetCategory = $this->normalize((string) $target->getCategory());
            $candidateCategory = $this->normalize((string) $candidate->getCategory());
            if ($targetCategory !== '' && $targetCategory === $candidateCategory) {
                $score += 45;
                $reasons[] = 'same category';
            }

            $targetBrand = $this->normalize((string) $target->getBrand());
            $candidateBrand = $this->normalize((string) $candidate->getBrand());
            if ($targetBrand !== '' && $targetBrand === $candidateBrand) {
                $score += 18;
                $reasons[] = 'same brand';
            }

            $priceSimilarity = $this->ratioSimilarity((float) $target->getPrice(), (float) $candidate->getPrice(), 0.45);
            if ($priceSimilarity > 0) {
                $score += $priceSimilarity * 20;
                if ($priceSimilarity >= 0.65) {
                    $reasons[] = 'similar price';
                }
            }

            $calorieSimilarity = 0.0;
            if ($target->getCalories() !== null && $candidate->getCalories() !== null) {
                $calorieSimilarity = $this->ratioSimilarity((float) $target->getCalories(), (float) $candidate->getCalories(), 0.35);
                $score += $calorieSimilarity * 8;
                if ($calorieSimilarity >= 0.7) {
                    $reasons[] = 'similar nutrition';
                }
            }

            $textSimilarity = $this->tokenSimilarity(
                (string) ($target->getName() . ' ' . $target->getDescription()),
                (string) ($candidate->getName() . ' ' . $candidate->getDescription())
            );
            if ($textSimilarity > 0) {
                $score += $textSimilarity * 15;
                if ($textSimilarity >= 0.35) {
                    $reasons[] = 'similar profile';
                }
            }

            $availabilityScore = min(1.0, ($candidate->getStock() ?? 0) / max(1, ($requestedQuantity * 4)));
            $score += $availabilityScore * 12;
            if (($candidate->getStock() ?? 0) >= ($requestedQuantity * 2)) {
                $reasons[] = 'good availability';
            }

            $freshnessBoost = $this->freshnessBoost($candidate);
            $score += $freshnessBoost;

            if (count($reasons) === 0) {
                $reasons[] = 'available alternative';
            }

            $scored[] = [
                'supplement' => $candidate,
                'score' => round($score, 2),
                'reasons' => array_values(array_unique($reasons)),
            ];
        }

        usort($scored, static function (array $left, array $right): int {
            if ($left['score'] === $right['score']) {
                return ($right['supplement']->getStock() ?? 0) <=> ($left['supplement']->getStock() ?? 0);
            }

            return $right['score'] <=> $left['score'];
        });

        return array_slice($scored, 0, $limit);
    }

    private function normalize(string $value): string
    {
        return strtolower(trim($value));
    }

    private function ratioSimilarity(float $source, float $candidate, float $maxRatioDelta): float
    {
        if ($source <= 0) {
            return 0.0;
        }

        $deltaRatio = abs($candidate - $source) / max(0.01, $source);
        if ($deltaRatio >= $maxRatioDelta) {
            return 0.0;
        }

        return max(0.0, 1.0 - ($deltaRatio / $maxRatioDelta));
    }

    private function tokenSimilarity(string $left, string $right): float
    {
        $leftTokens = $this->tokenize($left);
        $rightTokens = $this->tokenize($right);

        if (count($leftTokens) === 0 || count($rightTokens) === 0) {
            return 0.0;
        }

        $intersection = array_intersect($leftTokens, $rightTokens);
        $union = array_unique(array_merge($leftTokens, $rightTokens));
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

    private function freshnessBoost(Supplement $supplement): float
    {
        $updatedAt = $supplement->getUpdatedAt();
        if ($updatedAt === null) {
            return 0.0;
        }

        $updatedAtImmutable = new \DateTimeImmutable($updatedAt->format(DATE_ATOM));
        $ageDays = (new \DateTimeImmutable())->diff($updatedAtImmutable)->days ?? 0;
        if ($ageDays <= 14) {
            return 4.0;
        }

        if ($ageDays <= 45) {
            return 2.0;
        }

        return 0.0;
    }
}
