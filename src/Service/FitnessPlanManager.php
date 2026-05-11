<?php

namespace App\Service;

use App\Entity\FitnessPlan;

class FitnessPlanManager
{
    public function validate(FitnessPlan $plan): bool
    {
        $title = trim($plan->getTitle());
        if (mb_strlen($title) < 3) {
            throw new \InvalidArgumentException('Le titre du plan doit contenir au moins 3 caracteres.');
        }

        $estimatedMinutes = $plan->getEstimatedMinutes();
        if ($estimatedMinutes !== null && $estimatedMinutes <= 0) {
            throw new \InvalidArgumentException('Le temps estime doit etre superieur a 0.');
        }

        $exercises = $plan->getExercisesData();
        if (count($exercises) === 0) {
            throw new \InvalidArgumentException('Le plan doit contenir au moins un exercice.');
        }

        return true;
    }
}
