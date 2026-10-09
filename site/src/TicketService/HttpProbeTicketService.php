<?php

namespace App\TicketService;

use App\Service\HttpProbeService;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.ticket_service')]
final class HttpProbeTicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly HttpProbeService $httpProbe,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::HTTP_PROBE;
    }

    public function getLabel(): string
    {
        return 'Sonde HTTP (GET)';
    }

    public function getDescription(): string
    {
        return 'Effectue un GET sur une URL autorisée et retourne statut, extrait de corps et liens contact.';
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
        $url = trim((string) ($context->params['url'] ?? ''));
        $purpose = (string) ($context->params['purpose'] ?? 'http_probe');

        if ('' === $url) {
            return TicketServiceResult::fail('Paramètre url requis.');
        }

        $result = $this->httpProbe->probeGet($url, $purpose, $context->probeAllowlist);
        $status = $result['status_code'] ?? '?';

        return TicketServiceResult::ok(
            sprintf('GET %s → HTTP %s', $url, $status),
            $result,
            true,
            'triage',
        );
    }
}
