<?php

namespace App\Command;

use App\Input\ImapInputSource;
use App\Message\IngestTicketMessage;
use App\Message\PollImapMessage;
use App\Service\AppSettingsService;
use App\Service\ImapProcessedMessageStore;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:poll-imap',
    description: 'Diagnostic / exécution manuelle du poll IMAP (comme le scheduler)',
)]
final class PollImapCommand extends Command
{
    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly ImapInputSource $imap,
        private readonly ImapProcessedMessageStore $processedMessages,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dispatch', null, InputOption::VALUE_NONE, 'Ingérer les messages trouvés (sinon aperçu seul)')
            ->addOption('via-handler', null, InputOption::VALUE_NONE, 'Passer par PollImapMessage (respecte poll_enabled)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $imap = $this->settings->getImapConfig();

        $io->section('Configuration effective');
        $io->listing([
            'poll_enabled: '.($this->settings->isImapPollEnabled() ? 'oui' : 'non'),
            'use_fixture: '.(($imap['use_fixture'] ?? false) ? 'oui' : 'non'),
            'host: '.($imap['host'] ?? '(vide)'),
            'user: '.($imap['user'] ?? '(vide)'),
            'folder: '.($imap['folder_inbox'] ?? 'INBOX'),
            'lookback: '.($imap['poll_lookback'] ?? 20),
            'mode: '.(!(bool) ($imap['use_fixture'] ?? true) && $this->settings->hasConfiguredRealImapHost($imap) ? 'IMAP réel' : 'fixture'),
        ]);

        if ($input->getOption('via-handler')) {
            if (!$this->settings->isImapPollEnabled()) {
                $io->warning('poll_enabled=false — le handler scheduler ne ferait rien.');

                return Command::FAILURE;
            }
            $this->bus->dispatch(new PollImapMessage());
            $io->success('PollImapMessage dispatché (traitement async si worker actif).');

            return Command::SUCCESS;
        }

        try {
            $result = $this->imap->testConnection();
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$result['ok']) {
            $io->error($result['message']);

            return Command::FAILURE;
        }

        $io->writeln($result['message']);

        if (!$input->getOption('dispatch')) {
            $io->note('Ajoutez --dispatch pour ingérer, ou --via-handler pour le flux scheduler.');

            return Command::SUCCESS;
        }

        $pending = $this->imap->poll();
        foreach ($pending as $dto) {
            $this->bus->dispatch(new IngestTicketMessage($dto));
            $io->writeln(sprintf('Dispatch ingest %s (%s)', $dto->ticketNumber, $dto->sourceRef ?? ''));
        }

        $io->success(sprintf('%d message(s) dispatchés.', count($pending)));

        return Command::SUCCESS;
    }
}
