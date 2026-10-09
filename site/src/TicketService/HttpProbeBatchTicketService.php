<?php

namespace App\TicketService;

use App\Service\HttpProbeService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('app.ticket_service')]
final class HttpProbeBatchTicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
        #[Autowire('%app.http_probe_batch_max_urls%')]
        private readonly int $maxUrls,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::HTTP_PROBE_BATCH;
    }

    public function getLabel(): string
    {
        return 'Sonde HTTP batch (GET)';
    }

    public function getDescription(): string
    {
        return sprintf('Effectue jusqu’à %d GET parallèles séquentiels sur des URLs autorisées.', $this->maxUrls);
    }

    public function getCategory(): string
    {
        return 'investigation';
    }

    public function supportsRelaunch(): bool
    {
        return true;
    }

    public function execute(TicketServiceContext $context): TicketServiceResult
    {
        $urls = $context->params['urls'] ?? null;
        if (!is_array($urls) || [] === $urls) {
            return TicketServiceResult::fail('Paramètre urls requis (tableau non vide).');
        }

        $normalized = [];
        foreach ($urls as $url) {
            if (!is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ('' !== $url) {
                $normalized[] = $url;
            }
        }

        $normalized = array_values(array_unique($normalized));
        if ([] === $normalized) {
            return TicketServiceResult::fail('Aucune URL valide dans urls.');
        }

        if (count($normalized) > $this->maxUrls) {
            return TicketServiceResult::fail(sprintf('Maximum %d URLs par batch.', $this->maxUrls));
        }

        $purpose = (string) ($context->params['purpose'] ?? 'http_probe_batch');
        $batch = $this->httpProbe->probeGetBatch($normalized, $purpose, $context->probeAllowlist);

        return TicketServiceResult::ok(
            sprintf('Batch %d URL(s) exécuté.', count($normalized)),
            $batch,
            true,
            'triage',
        );
    }
}
