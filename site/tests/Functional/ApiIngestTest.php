<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApiIngestTest extends WebTestCase
{
    public function testIngestReturns202(): void
    {
        $client = static::createClient();
        $number = 'TKT-API-'.substr(uniqid(), -6);

        $client->request(
            'POST',
            '/api/v1/tickets/ingest',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer test-secret',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: json_encode([
                'ticket_number' => $number,
                'subject' => 'Test API',
                'body' => 'Hello',
            ], JSON_THROW_ON_ERROR),
        );

        $this->assertResponseStatusCodeSame(202);
        $payload = json_decode($client->getResponse()->getContent(), true);
        $this->assertSame('accepted', $payload['status']);
        $this->assertSame($number, $payload['ticket_number']);
    }

    public function testIngestUnauthorized(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/tickets/ingest', content: '{}');
        $this->assertResponseStatusCodeSame(401);
    }
}
