<?php

namespace App\Service;

use App\Entity\RegimeAlimentaire;
use App\Entity\Repas;

class JavaDietPlannerService
{
    public const HYDRATION_GOAL_GLASSES = 5;

    /**
     * @return array{
     *     bmi: float|null,
     *     type_sante: string,
     *     calories_cibles: int|null,
     *     repas_adequats: string
     * }
     */
    public function buildProfile(?float $taille, ?float $poids, ?int $age): array
    {
        if (!$taille || !$poids || !$age) {
            return [
                'bmi' => null,
                'type_sante' => '',
                'calories_cibles' => null,
                'repas_adequats' => '',
            ];
        }

        $heightMeters = $taille / 100;
        $bmi = $heightMeters > 0 ? round($poids / ($heightMeters * $heightMeters), 2) : null;
        $typeSante = $bmi !== null ? $this->determineHealthTypeAutomatic($bmi) : '';
        $calories = $typeSante !== '' ? $this->calculateCaloriesTarget($poids, $typeSante, $age) : null;

        return [
            'bmi' => $bmi,
            'type_sante' => $typeSante,
            'calories_cibles' => $calories,
            'repas_adequats' => $this->generateMealRecommendations($typeSante, $age),
        ];
    }

    public function applyProfile(RegimeAlimentaire $regime): void
    {
        $profile = $this->buildProfile(
            $regime->getTaille(),
            $regime->getPoids(),
            $regime->getAge()
        );

        $regime
            ->setBmi($profile['bmi'])
            ->setTypeSante($profile['type_sante'] !== '' ? $profile['type_sante'] : null)
            ->setCaloriesCibles($profile['calories_cibles'])
            ->setRepasAdequats($profile['repas_adequats'] !== '' ? $profile['repas_adequats'] : null);
    }

    public function determineHealthTypeAutomatic(float $bmi): string
    {
        if ($bmi < 18.5) {
            return 'sous_poids';
        }
        if ($bmi < 25) {
            return 'normal';
        }
        if ($bmi < 30) {
            return 'surpoids';
        }

        return 'obesite';
    }

    public function calculateCaloriesTarget(float $poids, ?string $typeSante, int $age): int
    {
        $calories = (int) round($poids * 30);

        if ($typeSante === 'sous_poids') {
            $calories += 250;
        } elseif ($typeSante === 'obesite') {
            $calories -= 350;
        } elseif ($typeSante === 'surpoids') {
            $calories -= 200;
        }

        if ($age > 45) {
            $calories -= 80;
        }

        return max(1200, $calories);
    }

    public function generateMealRecommendations(?string $typeSante, int $age): string
    {
        $text = match ($typeSante) {
            'sous_poids' => 'Augmentez les calories, proteines et glucides. Repas riches: avocat, noix, viandes, oeufs, riz complet.',
            'normal' => 'Equilibrez proteines, glucides et lipides. Privilegiez legumes, viandes maigres et cereales completes.',
            'surpoids' => 'Reduisez les calories, evitez les sucres raffines. Legumes verts, proteines maigres et glucides complexes.',
            'obesite' => 'Regime hypocalorique controle. Legumes, viandes blanches, limitation des feculents et des graisses.',
            'diabetique' => 'Repas a index glycemique modere, legumes, fibres et portions controlees.',
            'cardiaque' => 'Limitez le sel et les graisses saturees. Favorisez poisson, huile d olive et legumes.',
            'autre' => 'Choisissez des repas simples, frais et faciles a digerer selon votre tolerance.',
            default => '',
        };

        if ($text !== '' && $age >= 45) {
            $text .= ' Hydratation et fibres renforcees.';
        }

        return $text;
    }

    /**
     * @param list<Repas> $repas
     * @param array{calories:int,proteines:int,glucides:int,lipides:int} $totals
     * @return array{
     *     items: list<array{name:string,count:int}>,
     *     totals: array{calories:int,proteines:int,glucides:int,lipides:int}
     * }
     */
    public function buildShoppingList(array $repas, array $totals): array
    {
        $counts = [];

        foreach ($repas as $meal) {
            $sources = array_filter([
                $meal->getCommentaire(),
                $meal->getNomRepas(),
            ]);

            foreach ($sources as $source) {
                $parts = preg_split('/[,;\n\/\|]+/', mb_strtolower((string) $source)) ?: [];
                foreach ($parts as $part) {
                    $item = trim($part);
                    $item = preg_replace('/\s+/', ' ', $item ?? '');
                    if (!is_string($item) || $item === '') {
                        continue;
                    }
                    if (mb_strlen($item) < 3) {
                        continue;
                    }
                    if (preg_match('/^(source|confiance|photo|automatiquement|optionnel|repas)$/i', $item)) {
                        continue;
                    }
                    $counts[$item] = ($counts[$item] ?? 0) + 1;
                }
            }
        }

        arsort($counts);
        $items = [];
        foreach (array_slice($counts, 0, 16, true) as $name => $count) {
            $items[] = [
                'name' => $this->humanize($name),
                'count' => (int) $count,
            ];
        }

        return [
            'items' => $items,
            'totals' => $totals,
        ];
    }

    /**
     * @param array{calories:int,proteines:int,glucides:int,lipides:int} $totals
     * @param list<Repas> $repas
     * @return list<array{label:string,description:string,unlocked:bool,kind:string}>
     */
    public function buildAchievements(
        ?RegimeAlimentaire $regime,
        array $totals,
        array $repas,
        int $hydrationCount,
        ?array $todayBadges
    ): array {
        $target = (int) ($regime?->getCaloriesCibles() ?? 0);
        $remaining = $target > 0 ? max(0, $target - $totals['calories']) : 0;

        return [
            [
                'label' => 'Semaine parfaite',
                'description' => ($todayBadges['calories_ok'] ?? false)
                    ? 'Calories dans la cible.'
                    : ($target > 0 ? 'Encore '.$remaining.' kcal avant la cible.' : 'Definissez une cible calorique.'),
                'unlocked' => (bool) ($todayBadges['calories_ok'] ?? false),
                'kind' => 'target',
            ],
            [
                'label' => 'Hydratation OK',
                'description' => sprintf('Objectif : 1L par jour (%d/%d verres)', $hydrationCount, self::HYDRATION_GOAL_GLASSES),
                'unlocked' => $hydrationCount >= self::HYDRATION_GOAL_GLASSES,
                'kind' => 'hydration',
            ],
            [
                'label' => 'Calories respectees',
                'description' => count($repas) > 0 ? 'Journee en cours suivie.' : 'Ajoutez votre premier repas du jour.',
                'unlocked' => count($repas) > 0,
                'kind' => 'journal',
            ],
        ];
    }

    /**
     * @param list<Repas> $repas
     * @param array{calories:int,proteines:int,glucides:int,lipides:int} $totals
     */
    public function buildSuggestionLine(?RegimeAlimentaire $regime, array $repas, array $totals): string
    {
        if (!$regime) {
            return 'Create your first regime to unlock meal planning.';
        }

        if ($repas === []) {
            return 'Your calorie target is ready. Add a meal or use Scanner repas to start today.';
        }

        if (($regime->getCaloriesCibles() ?? 0) > 0 && $totals['calories'] < (int) $regime->getCaloriesCibles()) {
            return 'Weekly meal plan ready. Open Planning 7j to browse your meals.';
        }

        return 'Calories target reached. Keep tracking meals and hydration throughout the day.';
    }

    public function humanizeHealthType(?string $typeSante): string
    {
        return match ($typeSante) {
            'sous_poids' => 'Sous-poids',
            'normal' => 'Normal',
            'surpoids' => 'Surpoids',
            'obesite' => 'Obesite',
            'diabetique' => 'Diabetique',
            'cardiaque' => 'Cardiaque',
            'autre' => 'Autre',
            default => 'N/A',
        };
    }

    public function mealTypeLabel(string $type): string
    {
        $normalized = mb_strtolower(trim($type));

        return match (true) {
            str_contains($normalized, 'petit') || str_contains($normalized, 'breakfast') => 'Petit-dejeuner',
            str_contains($normalized, 'dej') || str_contains($normalized, 'lunch') => 'Dejeuner',
            str_contains($normalized, 'diner') || str_contains($normalized, 'dinner') || str_contains($normalized, 'soir') => 'Diner',
            default => 'Collation',
        };
    }

    private function humanize(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return mb_strtoupper(mb_substr($value, 0, 1)).mb_substr($value, 1);
    }
}
