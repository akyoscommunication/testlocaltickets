<?php

namespace App\Tests\Unit\TicketService;

use App\Service\HttpProbeService;
use App\TicketService\HttpProbeBatchTicketService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProbeBatchTicketServiceTest extends TestCase
{
    public function testBatchExecutesMultipleUrls(): void
    {
        $client = new MockHttpClient([
            new MockResponse('', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 404]),
        ]);
        $httpProbe = new HttpProbeService($client);
        $service = new HttpProbeBatchTicketService($httpProbe, 10);

        $ctx = new \App\TicketService\TicketServiceContext(
            ticket: new \App\Entity\TicketProcessing('T-1', 'webhook'),
            ticketNumber: 'T-1',
            phase: 'triage',
            analysisResult: [],
            params: [
                'urls' => ['https://caladair.com/a', 'https://caladair.com/b'],
                'purpose' => 'sample',
            ],
            probeAllowlist: ['caladair.com'],
        );

        $result = $service->execute($ctx);

        self::assertTrue($result->ok);
        self::assertTrue($result->shouldRelaunchAgent);
        self::assertSame(2, $result->data['requested_count'] ?? 0);
        self::assertCount(2, $result->data['results'] ?? []);
    }

    public function testBatchRejectsMoreThanMaxUrls(): void
    {
        $httpProbe = new HttpProbeService(new MockHttpClient());
        $service = new HttpProbeBatchTicketService($httpProbe, 3);

        $urls = [];
        for ($i = 0; $i < 4; ++$i) {
            $urls[] = 'https://caladair.com/page'.$i;
        }

        $ctx = new \App\TicketService\TicketServiceContext(
            ticket: new \App\Entity\TicketProcessing('T-2', 'webhook'),
            ticketNumber: 'T-2',
            phase: 'triage',
            analysisResult: [],
            params: ['urls' => $urls],
            probeAllowlist: ['caladair.com'],
        );

        $result = $service->execute($ctx);
        self::assertFalse($result->ok);
    }
}
