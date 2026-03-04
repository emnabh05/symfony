<?php

namespace App\Tests\Service;

use App\Entity\FitnessPlan;
use App\Service\FitnessPlanManager;
use PHPUnit\Framework\TestCase;

class FitnessPlanManagerTest extends TestCase
{
    public function testValidPlan(): void
    {
        $manager = new FitnessPlanManager();
        $plan = (new FitnessPlan())
            ->setTitle('Plan Full Body Debutant')
            ->setEstimatedMinutes(45)
            ->setExercisesData([
                ['name' => 'Squat', 'sets' => 3, 'reps' => 12],
            ]);

        self::assertTrue($manager->validate($plan));
    }

    public function testPlanWithShortTitle(): void
    {
        $manager = new FitnessPlanManager();
        $plan = (new FitnessPlan())
            ->setTitle('AB')
            ->setEstimatedMinutes(30)
            ->setExercisesData([
                ['name' => 'Push-up', 'sets' => 3, 'reps' => 10],
            ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre du plan doit contenir au moins 3 caracteres.');

        $manager->validate($plan);
    }

    public function testPlanWithoutExercises(): void
    {
        $manager = new FitnessPlanManager();
        $plan = (new FitnessPlan())
            ->setTitle('Plan Cardio')
            ->setEstimatedMinutes(25)
            ->setExercisesData([]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le plan doit contenir au moins un exercice.');

        $manager->validate($plan);
    }
}

