<?php

namespace App\Input;

use App\Dto\IngestTicketDto;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Entrée push-only : le poll scheduler ne fait rien (ingest via API HTTP).
 */
#[AutoconfigureTag('app.input_source')]
final class WebhookInputSource implements InputSourceInterface
{
    public function getKey(): string
    {
        return 'webhook';
    }

    public function poll(): array
    {
        return [];
    }
}
