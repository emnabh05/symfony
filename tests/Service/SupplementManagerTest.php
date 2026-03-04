<?php

namespace App\Tests\Service;

use App\Entity\Supplement;
use App\Service\SupplementManager;
use PHPUnit\Framework\TestCase;

class SupplementManagerTest extends TestCase
{
    public function testValidSupplement(): void
    {
        $manager = new SupplementManager();
        $supplement = (new Supplement())
            ->setName('Whey Protein')
            ->setCategory('Protein')
            ->setBrand('Fitopia')
            ->setPrice('199.99')
            ->setStock(25)
            ->setDescription('Supplement riche en proteines pour la recuperation musculaire.');

        self::assertTrue($manager->validate($supplement));
    }

    public function testSupplementWithInvalidPrice(): void
    {
        $manager = new SupplementManager();
        $supplement = (new Supplement())
            ->setName('Creatine')
            ->setCategory('Performance')
            ->setBrand('Fitopia')
            ->setPrice('0')
            ->setStock(10)
            ->setDescription('Creatine monohydrate de haute purete.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le prix du supplement doit etre superieur a 0.');

        $manager->validate($supplement);
    }

    public function testSupplementWithNegativeStock(): void
    {
        $manager = new SupplementManager();
        $supplement = (new Supplement())
            ->setName('Omega 3')
            ->setCategory('Health')
            ->setBrand('Fitopia')
            ->setPrice('59.90')
            ->setStock(-1)
            ->setDescription('Acides gras essentiels pour le bien-etre general.');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le stock du supplement doit etre superieur ou egal a 0.');

        $manager->validate($supplement);
    }
}
