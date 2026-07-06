<?php

declare(strict_types=1);

namespace Angle\EntityTrailBundle\Repository;

use Angle\EntityTrailBundle\Entity\EntityTrailLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EntityTrailLog>
 */
class EntityTrailLogRepository extends ServiceEntityRepository
{
    /**
     * Logical sort column → entity field, used by the DataTables endpoint.
     *
     * @var array<string, string>
     */
    private const SORT_MAP = [
        'entity'     => 'entityType',
        'record'     => 'entityCode',
        'action'     => 'action',
        'user'       => 'userLabel',
        'created_at' => 'createdAt',
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EntityTrailLog::class);
    }

    /**
     * All entries for a specific record, newest first.
     *
     * @return list<EntityTrailLog>
     */
    public function findByEntity(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.entityType = :type')
            ->andWhere('t.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Server-side DataTables query.
     *
     * @return array{recordsTotal: int, recordsFiltered: int, items: list<EntityTrailLog>}
     */
    public function findForDataTable(
        string $search,
        int $start,
        int $length,
        ?string $sortCol,
        string $sortDir,
        ?string $filterEntityType = null,
        ?string $filterAction = null,
        ?string $filterDateFrom = null,
        ?string $filterDateTo = null,
        ?int $filterUserId = null,
    ): array {
        $recordsTotal = (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb = $this->createQueryBuilder('t');

        if ($search !== '') {
            $qb->andWhere($qb->expr()->orX(
                't.entityCode = :search'
            ))->setParameter('search', $search);
        }

        if ($filterEntityType !== null && $filterEntityType !== '') {
            $qb->andWhere('t.entityType = :filterType')
                ->setParameter('filterType', $filterEntityType);
        }

        if ($filterAction !== null && $filterAction !== '') {
            $qb->andWhere('t.action = :filterAction')
                ->setParameter('filterAction', $filterAction);
        }

        $from = $this->parseFilterDate($filterDateFrom, false);
        if ($from !== null) {
            $qb->andWhere('t.createdAt >= :filterDateFrom')
                ->setParameter('filterDateFrom', $from);
        }

        $to = $this->parseFilterDate($filterDateTo, true);
        if ($to !== null) {
            $qb->andWhere('t.createdAt <= :filterDateTo')
                ->setParameter('filterDateTo', $to);
        }

        if ($filterUserId === 0) {
            // 0 is the "no user" sentinel (real user ids are positive).
            $qb->andWhere('t.userId IS NULL');
        } elseif ($filterUserId !== null) {
            $qb->andWhere('t.userId = :filterUserId')
                ->setParameter('filterUserId', $filterUserId);
        }

        $countQb = clone $qb;
        $recordsFiltered = (int) $countQb
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sortField = self::SORT_MAP[$sortCol] ?? 'createdAt';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $items = $qb
            ->orderBy('t.' . $sortField, $sortDir)
            ->setFirstResult(max(0, $start))
            ->setMaxResults($length > 0 ? $length : 25)
            ->getQuery()
            ->getResult();

        return [
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'items'           => $items,
        ];
    }

    /**
     * Parse a YYYY-MM-DD filter string into a day boundary, or null if absent/malformed.
     */
    private function parseFilterDate(?string $value, bool $endOfDay): ?\DateTimeImmutable
    {
        if ($value === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        return new \DateTimeImmutable($value . ($endOfDay ? ' 23:59:59' : ' 00:00:00'));
    }

    /**
     * Most recent modification for a record (for "last edited by" UI).
     */
    public function findLastUpdate(string $entityType, int $entityId): ?EntityTrailLog
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.entityType = :type')
            ->andWhere('t.entityId = :id')
            ->setParameter('type', $entityType)
            ->setParameter('id', $entityId)
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Count changes made by a user in a date range.
     */
    public function countByUser(int $userId, \DateTimeInterface $from, \DateTimeInterface $to): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->andWhere('t.userId = :userId')
            ->andWhere('t.createdAt BETWEEN :from AND :to')
            ->setParameter('userId', $userId)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Distinct entity types present in the trail, for the admin filter dropdown.
     *
     * @return list<string>
     */
    public function findDistinctEntityTypes(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('DISTINCT t.entityType AS entityType')
            ->orderBy('t.entityType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): string => (string) $row['entityType'], $rows);
    }

    /**
     * Distinct users present in the trail, for the admin filter dropdown.
     * The label comes from each user's most recent entry (highest id), so a
     * renamed user shows under their current cached name.
     *
     * @return list<array{userId: int, userLabel: string}>
     */
    public function findDistinctUsers(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.userId AS userId', 't.userLabel AS userLabel')
            ->andWhere('t.userId IS NOT NULL')
            ->andWhere(sprintf(
                't.id = (SELECT MAX(t2.id) FROM %s t2 WHERE t2.userId = t.userId)',
                EntityTrailLog::class,
            ))
            ->orderBy('t.userLabel', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'userId'    => (int) $row['userId'],
            'userLabel' => (string) $row['userLabel'],
        ], $rows);
    }
}
