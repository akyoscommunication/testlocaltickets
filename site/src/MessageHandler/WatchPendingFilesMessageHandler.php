<?php

namespace App\MessageHandler;

use App\Entity\FileExchange;
use App\Message\ProcessFileOutcomeMessage;
use App\Message\WatchPendingFilesMessage;
use App\FileStorage\TicketFileStorage;
use App\Repository\FileExchangeRepository;
use App\Repository\TicketProcessingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use League\Flysystem\FilesystemOperator;

#[AsMessageHandler]
final class WatchPendingFilesMessageHandler
{
    public function __construct(
        #[Autowire(service: 'ticket.storage')]
        private readonly FilesystemOperator $storage,
        private readonly FileExchangeRepository $fileExchanges,
        private readonly TicketFileStorage $ticketFiles,
        private readonly TicketProcessingRepository $tickets,
        private readonly MessageBusInterface $bus,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function __invoke(WatchPendingFilesMessage $message): void
    {
        foreach ($this->tickets->findLatest(200) as $ticket) {
            $ticketNumber = $ticket->getTicketNumber();
            foreach (['analysis-results', 'code-results'] as $folder) {
                $prefix = $ticketNumber.'/'.$folder;
                if (!$this->storage->directoryExists($prefix)) {
                    continue;
                }
                foreach ($this->storage->listContents($prefix, false) as $item) {
                    if (!$item->isFile() || !str_ends_with($item->path(), '.json')) {
                        continue;
                    }
                    $this->processResultFile($ticketNumber, $folder, basename($item->path()));
                }
            }
        }
    }

    private function processResultFile(string $ticketNumber, string $folder, string $file): void
    {
            $relative = $folder.'/'.$file;
            $exchange = $this->fileExchanges->findOneBy([
                'ticketNumber' => $ticketNumber,
                'logicalPath' => $relative,
            ]);
            if ($exchange?->getProcessedAt()) {
                return;
            }

            $phase = str_starts_with($folder, 'code') ? 'code_analysis' : 'triage';

            if (!$exchange) {
                $exchange = new FileExchange($ticketNumber, $relative);
                if ($this->ticketFiles->exists($ticketNumber, $relative)) {
                    $exchange->setContentHash(hash('sha256', $this->ticketFiles->read($ticketNumber, $relative)));
                }
                $this->em->persist($exchange);
                $this->em->flush();
            }

            $this->bus->dispatch(new ProcessFileOutcomeMessage($ticketNumber, $relative, $phase));
    }
}
