<?php

namespace App\Tests\Service;

use App\Entity\Repas;
use App\Service\DietMealManager;
use PHPUnit\Framework\TestCase;

class DietMealManagerTest extends TestCase
{
    public function testValidMeal(): void
    {
        $manager = new DietMealManager();
        $repas = (new Repas())
            ->setNomRepas('Poulet riz')
            ->setTypeRepas('dejeuner')
            ->setCalories(650);

        self::assertTrue($manager->validate($repas));
    }

    public function testMealWithInvalidType(): void
    {
        $manager = new DietMealManager();
        $repas = (new Repas())
            ->setNomRepas('Salade')
            ->setTypeRepas('brunch')
            ->setCalories(300);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le type de repas est invalide.');

        $manager->validate($repas);
    }

    public function testMealWithNegativeCalories(): void
    {
        $manager = new DietMealManager();
        $repas = (new Repas())
            ->setNomRepas('Soupe')
            ->setTypeRepas('diner')
            ->setCalories(-10);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Les calories doivent etre superieures ou egales a 0.');

        $manager->validate($repas);
    }
}

