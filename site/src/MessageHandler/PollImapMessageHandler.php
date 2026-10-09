<?php

namespace App\MessageHandler;

use App\Input\InputSourceRegistry;
use App\Message\IngestTicketMessage;
use App\Message\PollImapMessage;
use App\Service\AppSettingsService;
use App\Service\AutomationLogger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class PollImapMessageHandler
{
    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly InputSourceRegistry $inputSources,
        private readonly MessageBusInterface $bus,
        private readonly AutomationLogger $logger,
    ) {
    }

    public function __invoke(PollImapMessage $message): void
    {
        if (!$this->settings->isImapPollEnabled()) {
            $this->logger->info('Poll IMAP ignoré — activez le poll dans Paramètres (imap.poll_enabled).');

            return;
        }

        $pending = $this->inputSources->get('imap')->poll();
        $this->logger->info(sprintf('Poll IMAP — %d message(s) à ingérer', count($pending)));

        foreach ($pending as $dto) {
            $this->bus->dispatch(new IngestTicketMessage($dto));
            $this->logger->info('IMAP → ingest dispatch', $dto->ticketNumber, null, [
                'source_ref' => $dto->sourceRef,
            ]);
        }
    }
}
