<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Supplement;
use App\Entity\OrderItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrderItem>
 */
class OrderItemRepository extends ServiceEntityRepository
{
    private const CANCELED_STATUSES = ['CANCELED', 'CANCELLED', 'canceled', 'cancelled'];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrderItem::class);
    }

    /**
     * @return array<int, array{supplement: object, firstPurchasedAt: mixed, totalQuantity: mixed}>
     */
    public function findPurchasedSupplementsForUser(User $user): array
    {
        $normalizedEmail = strtolower(trim($user->getEmail()));

        $rows = $this->createQueryBuilder('oi')
            ->select('IDENTITY(oi.supplement) AS supplementId')
            ->addSelect('MIN(o.createdAt) AS firstPurchasedAt')
            ->addSelect('SUM(oi.quantity) AS totalQuantity')
            ->innerJoin('oi.order', 'o')
            ->andWhere('(o.user = :user OR (o.user IS NULL AND LOWER(o.email) = :email))')
            ->andWhere('o.status NOT IN (:canceledStatuses)')
            ->setParameter('user', $user)
            ->setParameter('email', $normalizedEmail)
            ->setParameter('canceledStatuses', self::CANCELED_STATUSES)
            ->groupBy('supplementId')
            ->orderBy('MIN(o.createdAt)', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return $this->hydratePurchasedSupplements($rows);
    }

    public function hasPurchasedSupplementForUser(User $user, int $supplementId): bool
    {
        $normalizedEmail = strtolower(trim($user->getEmail()));

        $count = (int) $this->createQueryBuilder('oi')
            ->select('COUNT(oi.id)')
            ->innerJoin('oi.order', 'o')
            ->innerJoin('oi.supplement', 's')
            ->andWhere('(o.user = :user OR (o.user IS NULL AND LOWER(o.email) = :email))')
            ->andWhere('o.status NOT IN (:canceledStatuses)')
            ->andWhere('s.id = :supplementId')
            ->setParameter('user', $user)
            ->setParameter('email', $normalizedEmail)
            ->setParameter('canceledStatuses', self::CANCELED_STATUSES)
            ->setParameter('supplementId', $supplementId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @param array<int, array{supplementId?: mixed, firstPurchasedAt?: mixed, totalQuantity?: mixed}> $rows
     * @return array<int, array{supplement: object, firstPurchasedAt: mixed, totalQuantity: mixed}>
     */
    private function hydratePurchasedSupplements(array $rows): array
    {
        if ($rows === []) {
            return [];
        }

        $supplementIds = array_values(array_filter(array_unique(array_map(
            static fn (array $row): int => (int) ($row['supplementId'] ?? 0),
            $rows
        ))));

        if ($supplementIds === []) {
            return [];
        }

        $supplements = $this->getEntityManager()->getRepository(Supplement::class)->findBy(['id' => $supplementIds]);
        $supplementById = [];

        foreach ($supplements as $supplement) {
            if ($supplement->getId() !== null) {
                $supplementById[$supplement->getId()] = $supplement;
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $supplementId = (int) ($row['supplementId'] ?? 0);
            $supplement = $supplementById[$supplementId] ?? null;
            if (!$supplement instanceof Supplement) {
                continue;
            }

            $result[] = [
                'supplement' => $supplement,
                'firstPurchasedAt' => $row['firstPurchasedAt'] ?? null,
                'totalQuantity' => $row['totalQuantity'] ?? 0,
            ];
        }

        return $result;
    }
}
