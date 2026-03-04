<?php

namespace App\Repository;

use App\Entity\Supplement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supplement>
 */
class SupplementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supplement::class);
    }

    /**
     * @return Supplement[]
     */
    public function findCatalogLimited(int $limit = 96): array
    {
        $limit = max(1, min(500, $limit));

        return $this->createQueryBuilder('s')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function save(Supplement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Supplement $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return Supplement[]
     */
    public function findInStockAlternatives(int $excludeSupplementId, int $requiredStock = 1, int $limit = 80): array
    {
        $requiredStock = max(1, $requiredStock);
        $limit = max(1, min(200, $limit));

        return $this->createQueryBuilder('s')
            ->andWhere('s.id != :excludeId')
            ->andWhere('s.stock >= :requiredStock')
            ->setParameter('excludeId', $excludeSupplementId)
            ->setParameter('requiredStock', $requiredStock)
            ->orderBy('s.stock', 'DESC')
            ->addOrderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Supplement[]
     */
    public function findInStockCatalog(int $limit = 250): array
    {
        $limit = max(1, min(500, $limit));

        return $this->createQueryBuilder('s')
            ->andWhere('s.stock > 0')
            ->orderBy('s.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
