<?php

namespace App\Service;

use App\Entity\Supplement;

class SupplementManager
{
    public function validate(Supplement $supplement): bool
    {
        $name = trim((string) $supplement->getName());
        if (mb_strlen($name) < 3) {
            throw new \InvalidArgumentException('Le nom du supplement doit contenir au moins 3 caracteres.');
        }

        $price = (float) $supplement->getPrice();
        if ($price <= 0) {
            throw new \InvalidArgumentException('Le prix du supplement doit etre superieur a 0.');
        }

        $stock = $supplement->getStock();
        if ($stock === null || $stock < 0) {
            throw new \InvalidArgumentException('Le stock du supplement doit etre superieur ou egal a 0.');
        }

        return true;
    }
}
