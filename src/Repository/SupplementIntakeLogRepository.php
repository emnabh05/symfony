<?php

namespace App\Repository;

use App\Entity\Supplement;
use App\Entity\SupplementIntakeLog;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
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
            ->select('l.supplementId AS supplementId', 'l.intakeDate AS intakeDate')
            ->andWhere('LOWER(l.userEmail) = :email')
            ->setParameter('email', mb_strtolower((string) $user->getEmail()))
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
            ->andWhere('LOWER(l.userEmail) = :email')
            ->andWhere('l.supplementId = :supplementId')
            ->andWhere('l.intakeDate = :intakeDate')
            ->setParameter('email', mb_strtolower((string) $user->getEmail()))
            ->setParameter('supplementId', (int) $supplement->getId())
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
        return $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
SELECT
    u.id AS userId,
    u.username AS username,
    u.first_name AS firstName,
    u.last_name AS lastName,
    l.user_email AS email,
    l.log_date AS intakeDate
FROM supplement_adherence_logs l
INNER JOIN users u
    ON LOWER(CONVERT(u.email USING utf8mb4)) COLLATE utf8mb4_unicode_ci
     = LOWER(CONVERT(l.user_email USING utf8mb4)) COLLATE utf8mb4_unicode_ci
WHERE l.log_date BETWEEN :startDate AND :endDate
ORDER BY u.id ASC, l.log_date ASC
SQL,
            [
                'startDate' => $startDate->format('Y-m-d'),
                'endDate' => $endDate->format('Y-m-d'),
            ],
            [
                'startDate' => ParameterType::STRING,
                'endDate' => ParameterType::STRING,
            ]
        );
    }

    /**
     * @return array<int, array{
     *     planId: int,
     *     userEmail: string,
     *     supplementId: int,
     *     dailyTargetUnits: int,
     *     plannedDays: int,
     *     startDate: string
     * }>
     */
    public function findActivePlanRows(): array
    {
        try {
            $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
                <<<'SQL'
SELECT
    p.id AS planId,
    LOWER(TRIM(p.user_email)) AS userEmail,
    p.supplement_id AS supplementId,
    p.daily_target_units AS dailyTargetUnits,
    p.planned_days AS plannedDays,
    p.start_date AS startDate
FROM supplement_adherence_plans p
WHERE p.is_active = 1
SQL
            );
        } catch (Exception) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'planId' => (int) ($row['planId'] ?? 0),
                'userEmail' => mb_strtolower(trim((string) ($row['userEmail'] ?? ''))),
                'supplementId' => (int) ($row['supplementId'] ?? 0),
                'dailyTargetUnits' => max(1, (int) ($row['dailyTargetUnits'] ?? 1)),
                'plannedDays' => max(1, (int) ($row['plannedDays'] ?? 1)),
                'startDate' => (string) ($row['startDate'] ?? ''),
            ];
        }, $rows);
    }

    /**
     * @param int[] $planIds
     * @return array<int, array{planId: int, logDate: string, units: int}>
     */
    public function findLoggedUnitsByPlanIdsBetween(
        array $planIds,
        \DateTimeImmutable $startDate,
        \DateTimeImmutable $endDate
    ): array {
        $planIds = array_values(array_filter(array_map('intval', $planIds), static fn (int $id): bool => $id > 0));
        if ($planIds === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($planIds), '?'));
        $sql = sprintf(
            <<<'SQL'
SELECT
    l.plan_id AS planId,
    l.log_date AS logDate,
    SUM(l.taken_units) AS units
FROM supplement_adherence_logs l
WHERE l.plan_id IN (%s)
  AND l.log_date BETWEEN ? AND ?
GROUP BY l.plan_id, l.log_date
ORDER BY l.plan_id ASC, l.log_date ASC
SQL,
            $placeholders
        );

        $params = array_merge(
            $planIds,
            [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]
        );
        $types = array_merge(
            array_fill(0, count($planIds), ParameterType::INTEGER),
            [ParameterType::STRING, ParameterType::STRING]
        );

        try {
            $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative($sql, $params, $types);
        } catch (Exception) {
            return [];
        }

        return array_map(static function (array $row): array {
            return [
                'planId' => (int) ($row['planId'] ?? 0),
                'logDate' => (string) ($row['logDate'] ?? ''),
                'units' => max(0, (int) ($row['units'] ?? 0)),
            ];
        }, $rows);
    }

    /**
     * @return array<string, string>
     */
    public function findOrderDisplayNames(): array
    {
        try {
            $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
                <<<'SQL'
SELECT
    LOWER(TRIM(o.email)) AS userEmail,
    MAX(TRIM(CONCAT(COALESCE(o.first_name, ''), ' ', COALESCE(o.last_name, '')))) AS displayName
FROM supplement_orders o
WHERE o.email IS NOT NULL
  AND TRIM(o.email) <> ''
GROUP BY LOWER(TRIM(o.email))
SQL
            );
        } catch (Exception) {
            return [];
        }

        $names = [];
        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row['userEmail'] ?? '')));
            if ($email === '') {
                continue;
            }

            $displayName = trim((string) ($row['displayName'] ?? ''));
            $names[$email] = $displayName !== '' ? $displayName : $email;
        }

        return $names;
    }

    public function findOrCreatePlanId(User $user, Supplement $supplement): int
    {
        $connection = $this->getEntityManager()->getConnection();
        $email = trim((string) $user->getEmail());
        $supplementId = (int) $supplement->getId();

        $existingId = $connection->fetchOne(
            'SELECT id FROM supplement_adherence_plans WHERE LOWER(user_email) = LOWER(:email) AND supplement_id = :supplementId LIMIT 1',
            [
                'email' => $email,
                'supplementId' => $supplementId,
            ]
        );

        if ($existingId !== false) {
            return (int) $existingId;
        }

        $connection->insert('supplement_adherence_plans', [
            'user_email' => $email,
            'supplement_id' => $supplementId,
            'supplement_name' => (string) $supplement->getName(),
            'daily_target_units' => 1,
            'planned_days' => max(1, (int) ($supplement->getRecommendedDurationDays() ?? 30)),
            'start_date' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'is_active' => 1,
        ]);

        return (int) $connection->lastInsertId();
    }
}
