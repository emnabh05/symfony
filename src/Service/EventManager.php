<?php

namespace App\Service;

use App\Entity\Event;

class EventManager
{
    public function validate(Event $event): bool
    {
        $title = trim($event->getTitre());
        if (mb_strlen($title) < 3) {
            throw new \InvalidArgumentException("Le titre de l'evenement doit contenir au moins 3 caracteres.");
        }

        if ($event->getCapacite() <= 0) {
            throw new \InvalidArgumentException("La capacite de l'evenement doit etre superieure a 0.");
        }

        $price = (float) $event->getPrixEvent();
        if ($price < 0) {
            throw new \InvalidArgumentException("Le prix de l'evenement doit etre superieur ou egal a 0.");
        }

        return true;
    }
}

