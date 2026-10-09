<?php

namespace App\MessageHandler;

use App\Entity\TicketProcessing;
use App\FileStorage\TicketFileStorage;
use App\Message\IngestTicketMessage;
use App\Message\RunTriageAgentMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AttachmentIngestService;
use App\Service\AutomationLogger;
use App\Service\TicketNumberGenerator;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class IngestTicketMessageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketProcessingRepository $tickets,
        private readonly TicketFileStorage $files,
        private readonly MessageBusInterface $bus,
        private readonly AttachmentIngestService $attachments,
        private readonly AutomationLogger $logger,
        private readonly TicketNumberGenerator $ticketNumbers,
        private readonly TicketWorkflow $ticketWorkflow,
        #[Autowire('%env(NODE_ID)%')]
        private readonly string $nodeId,
    ) {
    }

    public function __invoke(IngestTicketMessage $message): void
    {
        $dto = $message->dto;

        if (null !== $dto->sourceRef) {
            $existing = $this->tickets->findOneBy(['sourceRef' => $dto->sourceRef, 'inputSource' => $dto->inputSource]);
            if ($existing instanceof TicketProcessing) {
                $this->logger->info('Ingest idempotent — ticket déjà connu', $existing->getTicketNumber());

                return;
            }
        }

        $ticketNumber = $dto->ticketNumber;
        if (null !== $this->tickets->findOneBy(['ticketNumber' => $ticketNumber])) {
            $ticketNumber = $this->ticketNumbers->generate();
            $this->logger->info('Numéro ticket régénéré (collision batch)', $ticketNumber, null, [
                'requested' => $dto->ticketNumber,
                'source_ref' => $dto->sourceRef,
            ]);
        }

        $ticket = new TicketProcessing($ticketNumber, $dto->inputSource);
        $ticket->setSourceRef($dto->sourceRef);
        $ticket->setAssignedNodeId($dto->targetNodeId ?? $this->nodeId);
        $this->em->persist($ticket);
        $this->em->flush();

        $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_STORE);

        $this->files->ensureTicketTree($ticketNumber);
        $storedAttachments = $this->attachments->persist($ticketNumber, $dto->attachments);

        $payload = [
            'subject' => $dto->subject,
            'body' => $dto->body,
            'attachments' => [] !== $storedAttachments ? $storedAttachments : $dto->attachments,
            'raw' => $dto->rawPayload,
        ];
        if (isset($dto->rawPayload['from']) && is_string($dto->rawPayload['from'])) {
            $payload['from'] = $dto->rawPayload['from'];
        }
        if (isset($dto->rawPayload['signature']) && is_string($dto->rawPayload['signature'])) {
            $payload['signature'] = $dto->rawPayload['signature'];
        }
        $ticket->setPayload($payload);
        $this->em->flush();
        $this->files->write($ticketNumber, 'inbox.json', json_encode([
            'ticket_number' => $ticketNumber,
            'input_source' => $dto->inputSource,
            'subject' => $dto->subject,
            'body' => $dto->body,
            'ingested_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $this->logger->info('Ticket ingéré', $ticketNumber);

        $this->bus->dispatch(new RunTriageAgentMessage($ticketNumber));
    }
}
