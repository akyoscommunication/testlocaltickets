<?php

namespace App\Repository;

use App\Entity\TicketProcessing;
use App\Workflow\TicketWorkflow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TicketProcessing>
 */
class TicketProcessingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TicketProcessing::class);
    }

    /**
     * @return list<TicketProcessing>
     */
    public function findLatest(int $limit = 50): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<TicketProcessing>
     */
    public function findFiltered(
        ?string $status = null,
        ?string $inputSource = null,
        ?string $projectKey = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.updatedAt', 'DESC')
            ->setFirstResult(max(0, ($page - 1) * $perPage))
            ->setMaxResults($perPage);

        if (null !== $status && '' !== $status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if (null !== $inputSource && '' !== $inputSource) {
            $qb->andWhere('t.inputSource = :source')->setParameter('source', $inputSource);
        }

        if (null !== $projectKey && '' !== $projectKey) {
            $qb->andWhere('t.resolvedProjectKey = :project')->setParameter('project', $projectKey);
        }

        return $qb->getQuery()->getResult();
    }

    public function countFiltered(
        ?string $status = null,
        ?string $inputSource = null,
        ?string $projectKey = null,
    ): int {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)');

        if (null !== $status && '' !== $status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if (null !== $inputSource && '' !== $inputSource) {
            $qb->andWhere('t.inputSource = :source')->setParameter('source', $inputSource);
        }

        if (null !== $projectKey && '' !== $projectKey) {
            $qb->andWhere('t.resolvedProjectKey = :project')->setParameter('project', $projectKey);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * @return list<TicketProcessing>
     */
    public function findFinishedBefore(\DateTimeImmutable $before): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.status = :status')
            ->andWhere('t.finishedAt IS NOT NULL')
            ->andWhere('t.finishedAt < :before')
            ->setParameter('status', TicketWorkflow::STATUS_FINISHED)
            ->setParameter('before', $before)
            ->getQuery()
            ->getResult();
    }
}
