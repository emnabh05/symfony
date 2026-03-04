<?php

namespace App\Service;

use App\Entity\Repas;

class DietMealManager
{
    /** @var array<int, string> */
    private const ALLOWED_TYPES = ['petit_dejeuner', 'dejeuner', 'diner', 'collation'];

    public function validate(Repas $repas): bool
    {
        $mealName = trim($repas->getNomRepas());
        if (mb_strlen($mealName) < 2) {
            throw new \InvalidArgumentException('Le nom du repas doit contenir au moins 2 caracteres.');
        }

        $mealType = trim($repas->getTypeRepas());
        if (!in_array($mealType, self::ALLOWED_TYPES, true)) {
            throw new \InvalidArgumentException('Le type de repas est invalide.');
        }

        $calories = $repas->getCalories();
        if ($calories !== null && $calories < 0) {
            throw new \InvalidArgumentException('Les calories doivent etre superieures ou egales a 0.');
        }

        return true;
    }
}

