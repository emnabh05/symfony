<?php

namespace App\Service;

use App\Entity\User;

class NutritionRegimeCalculator
{
    public function calculate(User $user): ?array
    {
        $weight = $user->getWeight();
        if (!$weight || $weight <= 0) {
            return null;
        }

        $height = $user->getHeight();
        $gender = $user->getGender();
        $birthDate = $user->getBirthDate();

        $bmr = null;
        if ($birthDate && $gender && $height) {
            $age = $this->calculateAge($birthDate);
            $bmr = 10 * $weight + 6.25 * $height - 5 * $age + ($gender === 'male' ? 5 : -161);
        }
        if ($bmr === null || $bmr <= 0) {
            $bmr = 24 * $weight;
        }

        $activity = $this->activityMultiplier($user->getFitnessLevel());
        $calories = (int) round($bmr * $activity);

        $goal = $this->determineGoal($user);
        $calories = (int) round($calories + $this->goalCaloriesAdjustment($goal));

        $macros = $this->macroTargets($goal, $user);
        $proteinGrams = (int) round(($calories * $macros['protein']) / 4);
        $carbGrams = (int) round(($calories * $macros['carb']) / 4);
        $fatGrams = (int) round(($calories * $macros['fat']) / 9);

        $typeRegime = $this->determineDietType($user);
        $restrictions = $this->combineRestrictions($user);

        return [
            'objectif' => $goal,
            'type_regime' => $typeRegime,
            'calories' => $calories,
            'proteines' => $proteinGrams,
            'glucides' => $carbGrams,
            'lipides' => $fatGrams,
            'restrictions' => $restrictions,
        ];
    }

    private function calculateAge(\DateTimeInterface $birthDate): int
    {
        $now = new \DateTimeImmutable();
        return (int) $now->diff($birthDate)->y;
    }

    private function activityMultiplier(?string $level): float
    {
        return match ($level) {
            'advanced' => 1.725,
            'intermediate' => 1.55,
            'beginner' => 1.375,
            default => 1.2,
        };
    }

    private function determineGoal(User $user): string
    {
        $goals = $user->getFitnessGoals() ?? [];
        $goals = array_map('strtolower', $goals);

        if (in_array('weight_loss', $goals, true)) {
            return 'perte_poids';
        }
        if (in_array('muscle_gain', $goals, true)) {
            return 'prise_masse';
        }
        if (in_array('endurance', $goals, true)) {
            return 'endurance';
        }
        if (in_array('maintenance', $goals, true)) {
            return 'maintien';
        }

        return 'maintien';
    }

    private function goalCaloriesAdjustment(string $goal): int
    {
        return match ($goal) {
            'perte_poids' => -500,
            'prise_masse' => 300,
            'endurance' => 200,
            default => 0,
        };
    }

    private function macroTargets(string $goal, User $user): array
    {
        $prefs = $user->getDietaryPreferences() ?? [];
        $prefs = array_map('strtolower', $prefs);

        if (in_array('keto', $prefs, true)) {
            return ['protein' => 0.25, 'carb' => 0.05, 'fat' => 0.70];
        }

        return match ($goal) {
            'perte_poids' => ['protein' => 0.30, 'carb' => 0.40, 'fat' => 0.30],
            'prise_masse' => ['protein' => 0.30, 'carb' => 0.50, 'fat' => 0.20],
            'endurance' => ['protein' => 0.25, 'carb' => 0.55, 'fat' => 0.20],
            default => ['protein' => 0.25, 'carb' => 0.50, 'fat' => 0.25],
        };
    }

    private function determineDietType(User $user): string
    {
        $prefs = $user->getDietaryPreferences() ?? [];
        $prefs = array_map('strtolower', $prefs);

        if (in_array('keto', $prefs, true)) {
            return 'keto';
        }
        if (in_array('vegan', $prefs, true)) {
            return 'vegan';
        }
        if (in_array('vegetarian', $prefs, true)) {
            return 'vegetarien';
        }

        $conditions = $user->getHealthConditions() ?? [];
        $conditionsStr = strtolower(implode(' ', $conditions));
        if (str_contains($conditionsStr, 'diabet')) {
            return 'diabetique';
        }

        return 'normal';
    }

    private function combineRestrictions(User $user): ?string
    {
        $parts = [];
        $prefs = $user->getDietaryPreferences() ?? [];
        $conds = $user->getHealthConditions() ?? [];

        foreach ($prefs as $p) {
            $parts[] = $p;
        }
        foreach ($conds as $c) {
            $parts[] = $c;
        }

        $parts = array_values(array_filter(array_map('trim', $parts)));
        return $parts ? implode(', ', $parts) : null;
    }
}
