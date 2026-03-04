<?php

namespace App\EventSubscriber;

use App\Entity\RegimeAlimentaire;
use App\Entity\User;
use App\Service\NutritionRegimeCalculator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

class UserRegimeSubscriber implements EventSubscriber
{
    private NutritionRegimeCalculator $calculator;
    private EntityManagerInterface $em;
    private array $pendingUsers = [];
    private bool $processing = false;

    public function __construct(NutritionRegimeCalculator $calculator, EntityManagerInterface $em)
    {
        $this->calculator = $calculator;
        $this->em = $em;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::postFlush,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->queueUser($args->getObject());
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->queueUser($args->getObject());
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ($this->processing || empty($this->pendingUsers)) {
            return;
        }
        $this->processing = true;

        foreach ($this->pendingUsers as $user) {
            if (!$user instanceof User) {
                continue;
            }
            $plan = $this->calculator->calculate($user);
            if (!$plan) {
                continue;
            }

            $regime = $this->em->getRepository(RegimeAlimentaire::class)->findOneBy(
                ['user' => $user],
                ['dateMiseAJour' => 'DESC']
            );
            if (!$regime) {
                $regime = new RegimeAlimentaire();
                $regime->setUser($user);
            }

            $regime->setObjectif($plan['objectif']);
            $regime->setTypeRegime($plan['type_regime']);
            $regime->setCaloriesCible($plan['calories']);
            $regime->setProteinesCible($plan['proteines']);
            $regime->setGlucidesCible($plan['glucides']);
            $regime->setLipidesCible($plan['lipides']);
            $regime->setRestrictions($plan['restrictions']);
            $regime->setDateMiseAJour(new \DateTimeImmutable());

            $this->em->persist($regime);
        }

        $this->pendingUsers = [];
        $this->em->flush();
        $this->processing = false;
    }

    private function queueUser(object $entity): void
    {
        if (!$entity instanceof User) {
            return;
        }
        $this->pendingUsers[spl_object_hash($entity)] = $entity;
    }
}
