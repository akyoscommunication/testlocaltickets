<?php

namespace App\Repository;

use App\Entity\AutomationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AutomationLog>
 */
class AutomationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AutomationLog::class);
    }

    /**
     * @return list<AutomationLog>
     */
    public function findLatest(?string $ticketNumber = null, int $limit = 100): array
    {
        return $this->findPaginated($ticketNumber, 1, $limit);
    }

    /**
     * @return list<AutomationLog>
     */
    public function findPaginated(?string $ticketNumber = null, int $page = 1, int $perPage = 20): array
    {
        $qb = $this->createQueryBuilder('l')
            ->orderBy('l.createdAt', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        if (null !== $ticketNumber && '' !== $ticketNumber) {
            $qb->andWhere('l.ticketNumber = :tn')->setParameter('tn', $ticketNumber);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(?string $ticketNumber = null): int
    {
        $qb = $this->createQueryBuilder('l')
            ->select('COUNT(l.id)');

        if (null !== $ticketNumber && '' !== $ticketNumber) {
            $qb->andWhere('l.ticketNumber = :tn')->setParameter('tn', $ticketNumber);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
