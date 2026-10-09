<?php

namespace App\MessageHandler;

use App\FileStorage\TicketFileStorage;
use App\Message\ExecuteCloneRepoMessage;
use App\Message\RunCodeAgentMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AutomationLogger;
use App\Service\GitCloneService;
use App\Service\ProjectResolverService;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class ExecuteCloneRepoMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly ProjectResolverService $projects,
        private readonly GitCloneService $gitClone,
        private readonly TicketFileStorage $files,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
        private readonly AutomationLogger $logger,
        private readonly TicketWorkflow $ticketWorkflow,
        #[Autowire('%env(bool:APP_WORKSPACE_STUB)%')]
        private readonly bool $workspaceStub,
        #[Autowire('%env(file_storage_dir:FILE_STORAGE_DSN)%')]
        private readonly string $ticketStorageDir,
    ) {
    }

    public function __invoke(ExecuteCloneRepoMessage $message): void
    {
        $ticket = $this->tickets->findOneBy(['ticketNumber' => $message->ticketNumber]);
        if (null === $ticket) {
            throw new \RuntimeException('Ticket introuvable : '.$message->ticketNumber);
        }

        if ($this->ticketWorkflow->can($ticket, TicketWorkflow::TRANSITION_EXECUTE_ACTIONS)) {
            $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_EXECUTE_ACTIONS);
            $this->em->flush();
        }

        $config = $this->projects->getProject($message->projectKey);
        $workspaceRelative = 'code-workspaces/'.$message->projectKey;
        $dataDir = realpath($this->ticketStorageDir) ?: $this->ticketStorageDir;
        $workspaceAbsolute = $dataDir.'/'.$message->ticketNumber.'/'.$workspaceRelative;

        if ($this->workspaceStub) {
            if (!is_dir($workspaceAbsolute)) {
                mkdir($workspaceAbsolute, 0775, true);
                file_put_contents($workspaceAbsolute.'/.workspace-stub', 'stub');
            }
        } else {
            try {
                $this->gitClone->cloneShallow(
                    $config['repo'],
                    $workspaceAbsolute,
                    $config['default_branch'] ?? 'main',
                );
            } catch (\Throwable $e) {
                $this->ticketWorkflow->apply($ticket, TicketWorkflow::TRANSITION_CLONE_FAILED);
                $this->em->flush();
                $this->logger->error('Clone échoué', $message->ticketNumber, null, ['error' => $e->getMessage()]);

                throw $e;
            }
        }

        $runId = 'run_'.(new \DateTimeImmutable())->format('YmdHis').'_ctx';
        $contextPackRelative = sprintf('context-packs/%s.json', $runId);

        $lastTriage = $this->findLatestTriageResultPath($message->ticketNumber);

        $this->files->write($message->ticketNumber, $contextPackRelative, json_encode([
            'schema_version' => '1',
            'ticket_number' => $message->ticketNumber,
            'phase' => 'code_analysis',
            'triage' => [
                'run_id' => basename((string) $lastTriage, '.json'),
                'summary' => $ticket->getAnalysisSummary()['summary'] ?? '',
                'resolution_requested' => 'needs_code',
                'full_result_path' => $lastTriage,
            ],
            'workspace' => [
                'project_key' => $message->projectKey,
                'repo' => $config['repo'],
                'logical_path' => $workspaceRelative,
                'base_branch' => $config['default_branch'] ?? 'main',
                'work_branch' => null,
            ],
            'attachments' => $ticket->getPayload()['attachments'] ?? [],
            'conversation_rules' => 'Ne pas modifier hors périmètre du ticket.',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $ticket->setResolvedProjectKey($message->projectKey);
        $this->em->flush();

        $this->logger->info('Clone terminé, lancement code_analysis', $message->ticketNumber);

        $this->bus->dispatch(new RunCodeAgentMessage($message->ticketNumber, $contextPackRelative));
    }

    private function findLatestTriageResultPath(string $ticketNumber): string
    {
        $results = array_values(array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $path): bool => str_starts_with($path, 'analysis-results/') && str_ends_with($path, '.json'),
        ));
        if ([] === $results) {
            return 'analysis-results/unknown.json';
        }

        sort($results);

        return $results[array_key_last($results)];
    }
}
