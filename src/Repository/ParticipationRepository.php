<?php

namespace App\Repository;

use App\Entity\Participation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Participation>
 */
class ParticipationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Participation::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithEventTitles(): array
    {
        return $this->findAllWithEventTitlesFiltered(null, 'recent');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAllWithEventTitlesFiltered(?string $search = null, string $sort = 'recent'): array
    {
        $sortDirection = $sort === 'oldest' ? 'ASC' : 'DESC';
        $qb = $this->createQueryBuilder('p')
            ->select('p.id AS id')
            ->addSelect('p.nomParticipant AS nom')
            ->addSelect('p.emailParticipant AS email')
            ->addSelect('p.dateInscription AS dateInscription')
            ->addSelect('e.titre AS eventTitle')
            ->innerJoin('p.event', 'e')
            ->orderBy('p.dateInscription', $sortDirection);

        $search = trim((string) $search);
        if ($search !== '') {
            $qb
                ->andWhere('LOWER(p.nomParticipant) LIKE :search')
                ->setParameter('search', '%'.strtolower($search).'%');
        }

        return $qb->getQuery()->getArrayResult();
    }
}
