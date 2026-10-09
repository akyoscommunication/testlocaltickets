<?php

namespace App\TicketService;

use App\Entity\TicketProcessing;
use App\Message\RunCodeAgentMessage;
use App\Message\RunTriageAgentMessage;
use App\Service\AgentLoopGuard;
use App\Service\AutomationLogger;
use App\Service\ServiceExecutionRecorder;
use App\Service\TicketContextEnricher;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class TicketServiceOrchestrator
{
    public function __construct(
        private readonly TicketServiceRegistry $registry,
        private readonly ServiceExecutionRecorder $recorder,
        private readonly AgentLoopGuard $loopGuard,
        private readonly TicketContextEnricher $contextEnricher,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
        private readonly AutomationLogger $logger,
        private readonly TicketWorkflow $ticketWorkflow,
    ) {
    }

    /**
     * @param array<string, mixed> $analysisResult
     * @param array<string, mixed> $serviceRequest
     */
    public function dispatch(
        TicketProcessing $ticket,
        string $ticketNumber,
        string $phase,
        array $analysisResult,
        array $serviceRequest,
    ): void {
        $serviceKey = (string) ($serviceRequest['service'] ?? '');
        $params = is_array($serviceRequest['params'] ?? null) ? $serviceRequest['params'] : [];

        if ('' === $serviceKey) {
            $this->logger->error('service_request.service manquant', $ticketNumber, null, []);

            return;
        }

        if (!$this->registry->has($serviceKey)) {
            $this->logger->error('Service inconnu : '.$serviceKey, $ticketNumber, null, []);

            return;
        }

        $service = $this->registry->get($serviceKey);

        if ($service->supportsRelaunch() && !$this->loopGuard->canRelaunch($ticket)) {
            $this->logger->error(
                'Plafond boucles IA atteint',
                $ticketNumber,
                null,
                ['max' => $this->loopGuard->getMaxLoops(), 'service' => $serviceKey],
            );
            $this->executeServiceDirect(
                $ticket,
                $ticketNumber,
                $phase,
                TicketServiceKeys::REQUEST_HUMAN_ACTION,
                [
                    'human_action' => 'collect_server_logs',
                    'summary' => ($analysisResult['summary'] ?? '').' (plafond boucles IA atteint)',
                ],
                $analysisResult,
            );

            return;
        }

        $this->executeServiceDirect($ticket, $ticketNumber, $phase, $serviceKey, $params, $analysisResult);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $analysisResult
     */
    private function executeServiceDirect(
        TicketProcessing $ticket,
        string $ticketNumber,
        string $phase,
        string $serviceKey,
        array $params,
        array $analysisResult,
    ): void {
        $service = $this->registry->get($serviceKey);

        $context = new TicketServiceContext(
            ticket: $ticket,
            ticketNumber: $ticketNumber,
            phase: $phase,
            analysisResult: $analysisResult,
            params: $params,
            probeAllowlist: $this->contextEnricher->buildProbeAllowlist($ticket->getPayload()),
        );

        $result = $service->execute($context);
        $executionPath = $this->recorder->nextExecutionPath($ticketNumber);

        $this->recorder->record($ticketNumber, $executionPath, [
            'service' => $serviceKey,
            'params' => $params,
            'ok' => $result->ok,
            'summary' => $result->summary,
            'data' => $result->data,
            'summary_lines' => $result->data['summary_lines'] ?? [],
        ]);

        $this->logger->info(
            sprintf('Service %s → %s', $serviceKey, $result->summary),
            $ticketNumber,
            null,
            ['execution_path' => $executionPath, 'ok' => $result->ok],
        );

        if ($result->shouldRelaunchAgent) {
            $ticket->incrementLoopCount();
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_QUEUE_AGENT);
            $this->em->flush();

            if ('code_analysis' === $result->targetPhase) {
                $this->bus->dispatch(new RunCodeAgentMessage(
                    $ticketNumber,
                    $result->data['context_pack_path'] ?? sprintf(
                        'context-packs/%s/pack.json',
                        $ticket->getResolvedProjectKey() ?? 'unknown',
                    ),
                ));
            } else {
                $this->bus->dispatch(new RunTriageAgentMessage($ticketNumber));
            }

            $this->logger->info('Service → relance '.$result->targetPhase, $ticketNumber, null, [
                'loop_count' => $ticket->getLoopCount(),
                'service' => $serviceKey,
            ]);
        }
    }
}
