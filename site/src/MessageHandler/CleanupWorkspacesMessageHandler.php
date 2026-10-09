<?php

namespace App\MessageHandler;

use App\Message\CleanupWorkspacesMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AutomationLogger;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CleanupWorkspacesMessageHandler
{
    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly AutomationLogger $logger,
        #[Autowire(service: 'ticket.storage')]
        private readonly FilesystemOperator $storage,
        #[Autowire('%env(int:WORKSPACE_RETENTION_DAYS)%')]
        private readonly int $retentionDays,
    ) {
    }

    public function __invoke(CleanupWorkspacesMessage $message): void
    {
        $before = new \DateTimeImmutable(sprintf('-%d days', $this->retentionDays));
        $removed = 0;

        foreach ($this->tickets->findFinishedBefore($before) as $ticket) {
            $projectKey = $ticket->getResolvedProjectKey();
            if (null === $projectKey) {
                continue;
            }

            $workspacePath = $ticket->getTicketNumber().'/code-workspaces/'.$projectKey;
            if (!$this->storage->directoryExists($workspacePath)) {
                continue;
            }

            $this->storage->deleteDirectory($workspacePath);
            ++$removed;
            $this->logger->info('Workspace supprimé (rétention)', $ticket->getTicketNumber(), null, [
                'path' => $workspacePath,
            ]);
        }

        if ($removed > 0) {
            $this->logger->info('Cleanup workspaces terminé', null, null, ['removed' => $removed]);
        }
    }
}
