<?php

namespace App\Repository;

use App\Entity\Supplement;
use App\Entity\SupplementIntakeLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SupplementIntakeLog>
 */
class SupplementIntakeLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SupplementIntakeLog::class);
    }

    /**
     * @return array<int, array<string, bool>>
     */
    public function getDateMapByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.supplement) AS supplementId', 'l.intakeDate AS intakeDate')
            ->andWhere('l.user = :user')
            ->setParameter('user', $user)
            ->orderBy('l.intakeDate', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $map = [];

        foreach ($rows as $row) {
            $supplementId = (int) ($row['supplementId'] ?? 0);
            if ($supplementId <= 0) {
                continue;
            }

            $intakeDate = $row['intakeDate'] ?? null;
            if ($intakeDate instanceof \DateTimeInterface) {
                $key = $intakeDate->format('Y-m-d');
            } elseif (is_string($intakeDate) && trim($intakeDate) !== '') {
                $key = (new \DateTimeImmutable($intakeDate))->format('Y-m-d');
            } else {
                continue;
            }

            $map[$supplementId][$key] = true;
        }

        return $map;
    }

    public function existsForDate(User $user, Supplement $supplement, \DateTimeImmutable $date): bool
    {
        $count = (int) $this->createQueryBuilder('l')
            ->select('COUNT(l.id)')
            ->andWhere('l.user = :user')
            ->andWhere('l.supplement = :supplement')
            ->andWhere('l.intakeDate = :intakeDate')
            ->setParameter('user', $user)
            ->setParameter('supplement', $supplement)
            ->setParameter('intakeDate', $date->setTime(0, 0), Types::DATE_IMMUTABLE)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * @return array<int, array{
     *     userId: mixed,
     *     username: mixed,
     *     firstName: mixed,
     *     lastName: mixed,
     *     email: mixed,
     *     intakeDate: mixed
     * }>
     */
    public function findLeaderboardRowsBetween(
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        return $this->createQueryBuilder('l')
            ->select(
                'IDENTITY(l.user) AS userId',
                'u.username AS username',
                'u.firstName AS firstName',
                'u.lastName AS lastName',
                'u.email AS email',
                'l.intakeDate AS intakeDate'
            )
            ->innerJoin('l.user', 'u')
            ->andWhere('l.intakeDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate, Types::DATE_IMMUTABLE)
            ->setParameter('endDate', $endDate, Types::DATE_IMMUTABLE)
            ->orderBy('u.id', 'ASC')
            ->addOrderBy('l.intakeDate', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }
}
