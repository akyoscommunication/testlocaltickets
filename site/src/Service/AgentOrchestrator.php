<?php

namespace App\Service;

use App\Agent\AgentRunErrorFormatter;
use App\Agent\AgentRunRequest;
use App\Agent\AgentRunnerRegistry;
use App\Entity\AgentRun;
use App\Entity\TicketProcessing;
use App\FileStorage\TicketFileStorage;
use App\Message\ProcessFileOutcomeMessage;
use App\Repository\AgentRunRepository;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

final class AgentOrchestrator
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TicketFileStorage $files,
        private readonly PromptRenderer $promptRenderer,
        private readonly AgentRunnerRegistry $runners,
        private readonly AgentRunRepository $agentRuns,
        private readonly AutomationLogger $logger,
        private readonly MessageBusInterface $bus,
        private readonly TicketContextEnricher $contextEnricher,
        private readonly ServiceExecutionRecorder $serviceRecorder,
        private readonly AppSettingsService $settings,
        private readonly TicketWorkflow $ticketWorkflow,
        #[Autowire(param: 'agents')]
        private readonly array $agentsConfig,
        #[Autowire(param: 'projects')]
        private readonly array $projects,
        #[Autowire('%env(file_storage_dir:FILE_STORAGE_DSN)%')]
        private readonly string $ticketStorageDir,
    ) {
    }

    public function runPhase(TicketProcessing $ticket, string $phase, ?string $contextPackRelativePath = null): AgentRun
    {
        $this->em->refresh($ticket);
        $ticketNumber = $ticket->getTicketNumber();

        if (TicketWorkflow::STATUS_AGENT_RUNNING === $ticket->getStatus()) {
            $active = $this->agentRuns->findLatestRunningForTicket($ticket);
            if (null !== $active) {
                $this->logger->info('Agent run déjà actif — message ignoré', $ticketNumber, $active->getRunId(), [
                    'phase' => $active->getPhase(),
                ]);

                return $active;
            }
        }

        if (!$this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_START_AGENT)) {
            throw new \DomainException(sprintf(
                'Impossible de démarrer l’agent (phase %s) depuis l’état « %s ».',
                $phase,
                $ticket->getStatus(),
            ));
        }

        $runId = 'run_'.(new \DateTimeImmutable())->format('YmdHis').'_'.substr(bin2hex(random_bytes(4)), 0, 6);

        $this->files->ensureTicketTree($ticketNumber);

        $rendered = $this->promptRenderer->render($phase, $this->buildPromptVariables($ticket, $phase, $contextPackRelativePath));

        $agentRunDir = sprintf('agent-runs/%s', $runId);
        $promptRelative = $agentRunDir.'/prompt.txt';
        $fullPrompt = $rendered['system']."\n\n---\n\n".$rendered['user'];
        $this->files->write($ticketNumber, $promptRelative, $fullPrompt);
        $this->files->write($ticketNumber, $agentRunDir.'/prompt-meta.json', json_encode([
            'phase' => $phase,
            'template_key' => $rendered['template_key'],
            'template_version' => $rendered['template_version'],
            'profile' => $this->settings->getAgentProfile(),
        ], JSON_THROW_ON_ERROR));

        $requestRelative = match ($phase) {
            'triage' => sprintf('analysis-requests/%s.json', $runId),
            'code_analysis' => sprintf('code-analysis-requests/%s.json', $runId),
            default => throw new \InvalidArgumentException('Phase agent non gérée : '.$phase),
        };

        $resultRelative = match ($phase) {
            'triage' => sprintf('analysis-results/%s.json', $runId),
            'code_analysis' => sprintf('code-results/%s.json', $runId),
            default => throw new \InvalidArgumentException('Phase agent non gérée : '.$phase),
        };

        $requestPayload = $this->buildRequestPayload($ticket, $phase, $runId, $contextPackRelativePath);
        $this->files->write($ticketNumber, $requestRelative, json_encode($requestPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $agentRun = new AgentRun($ticket, $runId, $phase, $rendered['template_key'], $rendered['template_version']);
        $agentRun->setRenderedPromptPath($promptRelative);
        $agentRun->setRequestPath($requestRelative);
        $agentRun->setResponsePath($resultRelative);
        if ($contextPackRelativePath) {
            $agentRun->setContextPackPath($contextPackRelativePath);
        }
        $agentRun->setStatus('running');

        $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_START_AGENT);
        $this->em->persist($agentRun);
        $this->em->flush();

        $profile = $this->agentsConfig[$this->settings->getAgentProfile()] ?? ['timeout' => 600];
        $dataDir = realpath($this->ticketStorageDir) ?: $this->ticketStorageDir;

        $runnerRequest = new AgentRunRequest(
            $ticketNumber,
            $runId,
            $phase,
            $dataDir.'/'.$ticketNumber.'/'.$requestRelative,
            $dataDir.'/'.$ticketNumber.'/'.$promptRelative,
            $dataDir.'/'.$ticketNumber.'/'.$resultRelative,
            $dataDir.'/'.$ticketNumber.'/'.$agentRunDir,
            (int) ($profile['timeout'] ?? 600),
        );

        $runner = $this->runners->getActiveRunner();
        $this->logger->info('Agent run starting', $ticketNumber, $runId, [
            'phase' => $phase,
            'profile' => $this->settings->getAgentProfile(),
            'runner' => $runner->getName(),
        ]);

        $started = microtime(true);
        $result = $runner->run($runnerRequest);
        $durationMs = (int) ((microtime(true) - $started) * 1000);

        $agentRun->setDurationMs($durationMs);
        $agentRun->setFinishedAt(new \DateTimeImmutable());

        if (!$result->success) {
            $stderr = $result->stderr ?? '';
            $summary = $result->errorSummary ?? AgentRunErrorFormatter::summarize($stderr, $result->exitCode);
            $agentRun->setStatus('failed');
            $agentRun->setErrorMessage($summary);
            $this->markAgentFailed($ticket);
            $this->em->flush();
            $this->logger->error(
                'Agent run failed: '.$summary,
                $ticketNumber,
                $runId,
                [
                    'exit_code' => $result->exitCode,
                    'phase' => $phase,
                    'profile' => $this->settings->getAgentProfile(),
                    'runner' => $runner->getName(),
                    'error_summary' => $summary,
                    'stderr_tail' => AgentRunErrorFormatter::tail($stderr),
                    'stderr_log' => sprintf('agent-runs/%s/stderr.log', $runId),
                    'codex_stderr_log' => dirname($resultRelative).'/codex-exec.stderr.log',
                ],
            );

            return $agentRun;
        }

        $agentRun->setStatus('completed');
        $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_AGENT_COMPLETED);
        $this->em->flush();

        $this->logger->info('Agent run completed', $ticketNumber, $runId, ['phase' => $phase, 'ms' => $durationMs]);

        $this->bus->dispatch(new ProcessFileOutcomeMessage(
            $ticketNumber,
            $resultRelative,
            $phase,
        ));

        return $agentRun;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRequestPayload(TicketProcessing $ticket, string $phase, string $runId, ?string $contextPackPath): array
    {
        $base = [
            'schema_version' => '1',
            'ticket_number' => $ticket->getTicketNumber(),
            'phase' => $phase,
            'input_source' => $ticket->getInputSource(),
            'subject' => $ticket->getPayload()['subject'] ?? '',
            'body' => $ticket->getPayload()['body'] ?? '',
            'attachments' => $ticket->getPayload()['attachments'] ?? [],
        ];

        if ('triage' === $phase) {
            $catalog = [];
            foreach ($this->projects as $key => $config) {
                $catalog[] = ['key' => $key, 'domains' => $config['domains'] ?? []];
            }
            $base['projects_catalog'] = $catalog;
            $base = array_merge($base, $this->contextEnricher->enrichFromPayload($ticket->getPayload()));
            $servicesContext = $this->serviceRecorder->readContext($ticket->getTicketNumber());
            if (null !== $servicesContext) {
                $base['services_context'] = $servicesContext;
            }
        }

        if ('code_analysis' === $phase && $contextPackPath) {
            $base['context_pack_path'] = $contextPackPath;
        }

        return $base;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPromptVariables(TicketProcessing $ticket, string $phase, ?string $contextPackRelativePath): array
    {
        $payload = $ticket->getPayload();
        $vars = [
            'ticket_number' => $ticket->getTicketNumber(),
            'subject' => $payload['subject'] ?? '',
            'body' => $payload['body'] ?? '',
        ];

        if ('triage' === $phase) {
            $catalog = [];
            foreach ($this->projects as $key => $config) {
                $catalog[] = ['key' => $key, 'domains' => $config['domains'] ?? []];
            }
            $vars['projects_catalog'] = $catalog;
            $vars = array_merge($vars, $this->contextEnricher->enrichFromPayload($payload));
            $servicesContext = $this->serviceRecorder->readContext($ticket->getTicketNumber());
            if (null !== $servicesContext) {
                $vars['services_context'] = $servicesContext;
            }
        }

        if ('code_analysis' === $phase) {
            $projectKey = $ticket->getResolvedProjectKey() ?? 'unknown';
            $vars['workspace_path'] = 'code-workspaces/'.$projectKey;
            $vars['context_pack_path'] = $contextPackRelativePath ?? '';
            $vars['triage_result_path'] = 'analysis-results/'; // agent lit le pack
            $vars['conversation_rules'] = 'Ne pas modifier hors périmètre du ticket.';
        }

        return $vars;
    }

    private function markAgentFailed(TicketProcessing $ticket): void
    {
        $this->em->refresh($ticket);

        if ($this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_AGENT_FAILED)) {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_AGENT_FAILED);

            return;
        }

        if (TicketWorkflow::STATUS_AGENT_ERROR !== $ticket->getStatus()) {
            $ticket->setStatus(TicketWorkflow::STATUS_AGENT_ERROR);
        }
    }
}
