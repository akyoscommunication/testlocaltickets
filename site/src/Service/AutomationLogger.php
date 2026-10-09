<?php

namespace App\Service;

use App\Entity\AutomationLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AutomationLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $ticketAgentLogger,
    ) {
    }

    public function info(string $message, ?string $ticketNumber = null, ?string $runId = null, array $context = []): void
    {
        $this->log('info', $message, $ticketNumber, $runId, $context);
    }

    public function error(string $message, ?string $ticketNumber = null, ?string $runId = null, array $context = []): void
    {
        $this->log('error', $message, $ticketNumber, $runId, $context);
    }

    private function log(string $level, string $message, ?string $ticketNumber, ?string $runId, array $context): void
    {
        $this->ticketAgentLogger->log($level, $message, array_filter([
            'ticket_number' => $ticketNumber,
            'run_id' => $runId,
            ...$context,
        ]));

        $entry = (new AutomationLog($level, $message))
            ->setTicketNumber($ticketNumber)
            ->setRunId($runId)
            ->setContext($context ?: null);

        $this->em->persist($entry);
        $this->em->flush();
    }
}
