<?php

namespace App\MessageHandler;

use App\Entity\FileExchange;
use App\FileStorage\TicketFileStorage;
use App\Message\ProcessFileOutcomeMessage;
use App\Repository\FileExchangeRepository;
use App\Repository\TicketProcessingRepository;
use App\Service\AgentResultValidator;
use App\Service\AutomationLogger;
use App\TicketService\TicketServiceOrchestrator;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ProcessFileOutcomeMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly FileExchangeRepository $fileExchanges,
        private readonly TicketFileStorage $files,
        private readonly AgentResultValidator $validator,
        private readonly EntityManagerInterface $em,
        private readonly AutomationLogger $logger,
        private readonly TicketServiceOrchestrator $serviceOrchestrator,
        private readonly TicketWorkflow $ticketWorkflow,
    ) {
    }

    public function __invoke(ProcessFileOutcomeMessage $message): void
    {
        $existing = $this->fileExchanges->findOneBy([
            'ticketNumber' => $message->ticketNumber,
            'logicalPath' => $message->resultRelativePath,
        ]);
        if ($existing?->getProcessedAt()) {
            return;
        }

        $ticket = $this->tickets->findOneBy(['ticketNumber' => $message->ticketNumber]);
        if (null === $ticket) {
            throw new \RuntimeException('Ticket introuvable : '.$message->ticketNumber);
        }

        $json = $this->files->read($message->ticketNumber, $message->resultRelativePath);
        $data = $this->validator->validateAndParse($json, $message->phase);

        if (!$existing) {
            $existing = new FileExchange($message->ticketNumber, $message->resultRelativePath);
            $this->em->persist($existing);
        }
        $existing->setContentHash(hash('sha256', $json));
        $existing->markProcessed();

        $serviceRequest = is_array($data['service_request'] ?? null) ? $data['service_request'] : null;

        $ticket->setLastResolution($data['resolution'] ?? null);
        $ticket->setAnalysisSummary([
            'summary' => $data['summary'] ?? null,
            'client_reply_draft' => $data['client_reply_draft'] ?? null,
            'internal_note' => $data['internal_note'] ?? null,
            'confidence' => $data['confidence'] ?? null,
            'resolution' => $data['resolution'] ?? null,
            'service' => $serviceRequest['service'] ?? null,
            'human_action' => $data['service_request']['params']['human_action'] ?? null,
        ]);
        if ($this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_PROCESS_OUTCOME)) {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_PROCESS_OUTCOME);
        } else {
            $this->logger->error(
                sprintf('Transition process_outcome impossible depuis « %s »', $ticket->getStatus()),
                $message->ticketNumber,
                null,
                ['result_path' => $message->resultRelativePath],
            );
        }
        $this->em->flush();

        if ('triage' === $message->phase && null !== $serviceRequest) {
            $this->logger->info(
                sprintf('Analyse %s — service %s', $message->phase, $serviceRequest['service'] ?? '?'),
                $message->ticketNumber,
                null,
                ['resolution' => $data['resolution'] ?? null],
            );
            $this->serviceOrchestrator->dispatch(
                $ticket,
                $message->ticketNumber,
                $message->phase,
                $data,
                $serviceRequest,
            );

            return;
        }

        if ('code_analysis' === $message->phase) {
            $this->handleCodeAnalysisLegacy($ticket, $message, $data);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleCodeAnalysisLegacy(\App\Entity\TicketProcessing $ticket, ProcessFileOutcomeMessage $message, array $data): void
    {
        $nextStep = (string) ($data['next_step'] ?? '');
        $this->logger->info('Code analysis next_step legacy : '.$nextStep, $message->ticketNumber, null, []);

        match ($nextStep) {
            'close' => $this->serviceOrchestrator->dispatch(
                $ticket,
                $message->ticketNumber,
                $message->phase,
                $data,
                ['service' => 'close_ticket', 'params' => []],
            ),
            'wait_human', 'prepare_mr' => $this->serviceOrchestrator->dispatch(
                $ticket,
                $message->ticketNumber,
                $message->phase,
                $data,
                [
                    'service' => 'request_human_action',
                    'params' => [
                        'human_action' => (string) ($data['human_action'] ?? 'collect_server_logs'),
                        'summary' => (string) ($data['summary'] ?? ''),
                    ],
                ],
            ),
            'agent_loop' => $this->serviceOrchestrator->dispatch(
                $ticket,
                $message->ticketNumber,
                $message->phase,
                $data,
                ['service' => 'rerun_agent', 'params' => ['phase' => 'code_analysis']],
            ),
            default => $this->logger->error('next_step code inconnu', $message->ticketNumber, null, ['next_step' => $nextStep]),
        };
    }
}
