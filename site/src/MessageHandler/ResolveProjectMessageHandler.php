<?php

namespace App\MessageHandler;

use App\Message\ExecuteCloneRepoMessage;
use App\Message\ResolveProjectMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AutomationLogger;
use App\Service\ProjectResolverService;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ResolveProjectMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly ProjectResolverService $resolver,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly AutomationLogger $logger,
        private readonly TicketWorkflow $ticketWorkflow,
    ) {
    }

    public function __invoke(ResolveProjectMessage $message): void
    {
        $ticket = $this->tickets->findOneBy(['ticketNumber' => $message->ticketNumber]);
        if (null === $ticket) {
            throw new \RuntimeException('Ticket introuvable : '.$message->ticketNumber);
        }

        $payload = $ticket->getPayload();
        $sender = $payload['raw']['from'] ?? $payload['raw']['sender'] ?? null;

        $result = $this->resolver->resolve(
            $message->analysisResult['project_detection'] ?? null,
            is_string($sender) ? $sender : null,
            $payload['body'] ?? null,
        );

        if ('auto' === $result['status'] && $result['project_key']) {
            $ticket->setResolvedProjectKey($result['project_key']);
            $ticket->setRepoResolution('auto');
            $this->em->flush();

            $this->bus->dispatch(new ExecuteCloneRepoMessage($message->ticketNumber, $result['project_key']));

            return;
        }

        $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_WAIT_HUMAN);
        $ticket->setRepoResolution('failed' === $result['status'] ? 'failed' : null);
        $this->em->flush();

        $this->logger->info('Résolution repo manuelle requise', $message->ticketNumber, null, [
            'candidates' => $result['candidates'],
        ]);
    }
}
