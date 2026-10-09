<?php

namespace App\TicketService;

use App\FileStorage\TicketFileStorage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.ticket_service')]
final class RerunAgentService implements TicketServiceInterface
{
    public function __construct(
        private readonly TicketFileStorage $files,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::RERUN_AGENT;
    }

    public function getLabel(): string
    {
        return 'Relancer l’agent IA';
    }

    public function getDescription(): string
    {
        return 'Relance triage ou analyse code sans autre action intermédiaire.';
    }

    public function getCategory(): string
    {
        return 'agent';
    }

    public function supportsRelaunch(): bool
    {
        return true;
    }

    public function execute(TicketServiceContext $context): TicketServiceResult
    {
        $phase = (string) ($context->params['phase'] ?? $context->phase);
        if (!in_array($phase, ['triage', 'code_analysis'], true)) {
            $phase = 'triage';
        }

        if ('code_analysis' === $phase) {
            if (null === $context->ticket->getResolvedProjectKey()) {
                return TicketServiceResult::fail('Projet non résolu pour code_analysis.');
            }
            $contextPack = $this->findLatestContextPackPath($context->ticketNumber)
                ?? sprintf('context-packs/%s/pack.json', $context->ticket->getResolvedProjectKey());

            return TicketServiceResult::ok(
                'Relance code_analysis.',
                ['context_pack_path' => $contextPack],
                true,
                'code_analysis',
            );
        }

        return TicketServiceResult::ok('Relance triage.', [], true, 'triage');
    }

    private function findLatestContextPackPath(string $ticketNumber): ?string
    {
        $packs = array_values(array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $path): bool => str_starts_with($path, 'context-packs/') && str_ends_with($path, '.json'),
        ));
        if ([] === $packs) {
            return null;
        }
        sort($packs);

        return $packs[array_key_last($packs)];
    }
}
