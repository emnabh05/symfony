<?php

namespace App\EventSubscriber;

use App\Entity\RegimeAlimentaire;
use App\Entity\User;
use App\Service\NutritionRegimeCalculator;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;

class UserRegimeSubscriber implements EventSubscriber
{
    private NutritionRegimeCalculator $calculator;
    private EntityManagerInterface $em;
    /** @var array<string, User> */
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

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->queueUser($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
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
            $plan = $this->calculator->calculate($user);
            if (!$plan) {
                continue;
            }

            $regime = $this->em->getRepository(RegimeAlimentaire::class)->findOneBy(
                ['user' => $user],
                ['id' => 'DESC']
            );
            if (!$regime) {
                $regime = new RegimeAlimentaire();
                $regime->setUser($user);
            }

            $regime->setCaloriesCibles($plan['calories']);
            $regime->setTypeSante($plan['type_regime']);
            $regime->setRepasAdequats(
                sprintf(
                    'objectif=%s; proteines=%d; glucides=%d; lipides=%d; restrictions=%s',
                    $plan['objectif'],
                    $plan['proteines'],
                    $plan['glucides'],
                    $plan['lipides'],
                    $plan['restrictions'] ?? ''
                )
            );

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
