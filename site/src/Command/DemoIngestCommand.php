<?php

namespace App\Command;

use App\Dto\IngestTicketDto;
use App\Message\IngestTicketMessage;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(name: 'app:demo-ingest', description: 'Ingère un ticket de démo (webhook simulé)')]
final class DemoIngestCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('ticket_number', InputArgument::OPTIONAL, 'Numéro ticket', 'T-DEMO-'.date('Ymd-His'))
            ->addOption('bug', null, InputOption::VALUE_NONE, 'Simuler un ticket technique (needs_code)')
            ->addOption('body', null, InputOption::VALUE_REQUIRED, 'Corps du message (remplace le texte par défaut)')
            ->addOption('subject', null, InputOption::VALUE_REQUIRED, 'Sujet du ticket')
            ->addOption('from', null, InputOption::VALUE_REQUIRED, 'Adresse e-mail expéditeur (ex. client@example.com)')
            ->addOption('signature', null, InputOption::VALUE_REQUIRED, 'Signature mail (nom, entreprise, domaine)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $ticketNumber = (string) $input->getArgument('ticket_number');
        $isBug = $input->getOption('bug');
        $customBody = $input->getOption('body');

        $body = is_string($customBody) && '' !== $customBody
            ? $customBody
            : ($isBug
                ? 'Erreur technique en production — merci de vérifier.'
                : 'Merci de mettre à jour le texte de la page d\'accueil.');

        $defaultSubject = $isBug
            ? 'Incident technique'
            : 'Mise à jour contenu';
        $subject = (string) ($input->getOption('subject') ?: $defaultSubject);

        $from = $input->getOption('from');
        $signature = $input->getOption('signature');
        $rawPayload = ['demo' => true];
        if (is_string($from) && '' !== $from) {
            $rawPayload['from'] = $from;
        }
        if (is_string($signature) && '' !== $signature) {
            $rawPayload['signature'] = $signature;
        }

        $dto = new IngestTicketDto(
            ticketNumber: $ticketNumber,
            inputSource: 'webhook',
            subject: $subject,
            body: $body,
            sourceRef: 'demo-'.uniqid(),
            rawPayload: $rawPayload,
        );

        $this->bus->dispatch(new IngestTicketMessage($dto));

        $io->success(sprintf('Ticket %s envoyé à la queue. Lance : php bin/console messenger:consume async -vv', $ticketNumber));

        return Command::SUCCESS;
    }
}
