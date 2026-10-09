<?php

namespace App\Tests\Unit\Service;

use App\Service\HttpProbeService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HttpProbeServiceTest extends TestCase
{
    public function testProbeGetReturnsStatusAndContactLinks(): void
    {
        $html = '<html><a href="/nous-contacter">Contact</a></html>';
        $client = new MockHttpClient([
            new MockResponse($html, ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']]),
        ]);
        $service = new HttpProbeService($client);

        $result = $service->probeGet('https://caladair.com/', 'homepage', ['caladair.com']);

        self::assertSame(200, $result['status_code']);
        self::assertContains('https://caladair.com/nous-contacter', $result['contact_links']);
        self::assertNull($result['error']);
    }

    public function testProbeRejectsHostOutsideAllowlist(): void
    {
        $client = new MockHttpClient();
        $service = new HttpProbeService($client);

        $result = $service->probeGet('https://evil.example/', 'test', ['caladair.com']);

        self::assertNotNull($result['error']);
        self::assertStringContainsString('allowlist', strtolower($result['error']));
    }

    public function testProbeRejectsLocalhost(): void
    {
        $client = new MockHttpClient();
        $service = new HttpProbeService($client);

        $result = $service->probeGet('http://localhost/', 'test', ['localhost']);

        self::assertNotNull($result['error']);
    }
}
