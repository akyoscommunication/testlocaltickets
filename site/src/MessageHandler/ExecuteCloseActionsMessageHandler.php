<?php

namespace App\MessageHandler;

use App\Message\ExecuteCloseActionsMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AutomationLogger;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ExecuteCloseActionsMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly EntityManagerInterface $em,
        private readonly AutomationLogger $logger,
        private readonly TicketWorkflow $ticketWorkflow,
    ) {
    }

    public function __invoke(ExecuteCloseActionsMessage $message): void
    {
        $ticket = $this->tickets->findOneBy(['ticketNumber' => $message->ticketNumber]);
        if (null === $ticket) {
            throw new \RuntimeException('Ticket introuvable : '.$message->ticketNumber);
        }

        $ticket->setAnalysisSummary([
            'summary' => $message->analysisResult['summary'] ?? null,
            'client_reply_draft' => $message->analysisResult['client_reply_draft'] ?? null,
            'internal_note' => $message->analysisResult['internal_note'] ?? null,
            'confidence' => $message->analysisResult['confidence'] ?? null,
        ]);

        if ('imap' === $ticket->getInputSource()) {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_WAIT_HUMAN);
            $this->em->flush();
            $this->logger->info('Clôture IMAP → waiting_for_human', $message->ticketNumber);

            return;
        }

        $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_FINISH);
        $ticket->setFinishedAt(new \DateTimeImmutable());
        $this->em->flush();

        $this->logger->info('Clôture webhook → finished (sans callback Phase 1)', $message->ticketNumber);
    }
}
