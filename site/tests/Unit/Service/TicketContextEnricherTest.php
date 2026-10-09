<?php

namespace App\Tests\Unit\Service;

use App\Service\ProjectResolverService;
use App\Service\TicketContextEnricher;
use PHPUnit\Framework\TestCase;

final class TicketContextEnricherTest extends TestCase
{
    public function testEnrichExtractsCaladairDomain(): void
    {
        $projects = ['caladair' => ['domains' => ['caladair.com']]];
        $enricher = new TicketContextEnricher(new ProjectResolverService($projects), $projects);

        $enriched = $enricher->enrichFromPayload([
            'body' => "formulaire 500\n\nHervé Nuzzo\ncaladair.com",
            'from' => 'herve.nuzzo@caladair.com',
            'signature' => 'Hervé Nuzzo — Caladair',
        ]);

        self::assertSame('herve.nuzzo@caladair.com', $enriched['sender_email']);
        self::assertContains('caladair.com', $enriched['extracted_domains']);
        self::assertSame('caladair.com', $enriched['inferred_site_domain']);
    }
}
