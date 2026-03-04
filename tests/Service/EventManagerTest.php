<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Service\EventManager;
use PHPUnit\Framework\TestCase;

class EventManagerTest extends TestCase
{
    public function testValidEvent(): void
    {
        $manager = new EventManager();
        $event = (new Event())
            ->setTitre('Bootcamp Outdoor')
            ->setDescription('Session collective full body')
            ->setLieu('Lac 1')
            ->setTypeEvent('Sport')
            ->setDateEvent(new \DateTimeImmutable('+7 days'))
            ->setCapacite(30)
            ->setPrixEvent('25.00')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        self::assertTrue($manager->validate($event));
    }

    public function testEventWithInvalidCapacity(): void
    {
        $manager = new EventManager();
        $event = (new Event())
            ->setTitre('Yoga')
            ->setDescription('Seance yoga')
            ->setLieu('Salle A')
            ->setTypeEvent('Bien-etre')
            ->setDateEvent(new \DateTimeImmutable('+3 days'))
            ->setCapacite(0)
            ->setPrixEvent('10.00')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("La capacite de l'evenement doit etre superieure a 0.");

        $manager->validate($event);
    }

    public function testEventWithNegativePrice(): void
    {
        $manager = new EventManager();
        $event = (new Event())
            ->setTitre('Run Club')
            ->setDescription('Sortie running')
            ->setLieu('Parc')
            ->setTypeEvent('Cardio')
            ->setDateEvent(new \DateTimeImmutable('+2 days'))
            ->setCapacite(20)
            ->setPrixEvent('-5.00')
            ->setCreatedAt(new \DateTimeImmutable('now'));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le prix de l'evenement doit etre superieur ou egal a 0.");

        $manager->validate($event);
    }
}

