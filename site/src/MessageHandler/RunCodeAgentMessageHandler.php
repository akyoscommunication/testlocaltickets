<?php

namespace App\MessageHandler;

use App\Message\RunCodeAgentMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AgentOrchestrator;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunCodeAgentMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly AgentOrchestrator $orchestrator,
    ) {
    }

    public function __invoke(RunCodeAgentMessage $message): void
    {
        $ticket = $this->tickets->findOneBy(['ticketNumber' => $message->ticketNumber]);
        if (null === $ticket) {
            throw new \RuntimeException('Ticket introuvable : '.$message->ticketNumber);
        }

        $this->orchestrator->runPhase($ticket, 'code_analysis', $message->contextPackPath);
    }
}
