<?php

namespace App\Repository;

use App\Entity\AgentRun;
use App\Entity\TicketProcessing;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AgentRun>
 */
class AgentRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AgentRun::class);
    }

    /**
     * @return list<AgentRun>
     */
    public function findByTicketOrdered(TicketProcessing $ticket): array
    {
        return $this->findByTicketNumber($ticket->getTicketNumber());
    }

    /**
     * @return list<AgentRun>
     */
    public function findByTicketNumber(string $ticketNumber): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.ticketProcessing', 't')
            ->andWhere('t.ticketNumber = :ticketNumber')
            ->setParameter('ticketNumber', $ticketNumber)
            ->orderBy('r.startedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestRunningForTicket(TicketProcessing $ticket): ?AgentRun
    {
        $runs = $this->createQueryBuilder('r')
            ->andWhere('r.ticketProcessing = :ticket')
            ->andWhere('r.status = :status')
            ->setParameter('ticket', $ticket)
            ->setParameter('status', 'running')
            ->orderBy('r.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $runs[0] ?? null;
    }
}
