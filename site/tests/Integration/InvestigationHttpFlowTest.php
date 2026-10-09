<?php

namespace App\Tests\Integration;

use App\Entity\TicketProcessing;
use App\Message\ProcessFileOutcomeMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\ServiceExecutionRecorder;
use App\FileStorage\TicketFileStorage;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\TraceableMessageBus;

final class InvestigationHttpFlowTest extends KernelTestCase
{
    public function testHttpProbeCreatesServiceExecutionAndRelaunchesTriage(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $mockClient = new MockHttpClient([
            new MockResponse(
                '<html><a href="/contact">Contact</a></html>',
                ['http_code' => 200, 'response_headers' => ['content-type' => 'text/html']],
            ),
        ]);
        $container->set('http_client', $mockClient);

        $em = $container->get(EntityManagerInterface::class);
        $files = $container->get(TicketFileStorage::class);
        $number = 'TKT-HTTP-'.substr(uniqid(), -6);

        $ticket = new TicketProcessing($number, 'webhook');
        $ticket->setPayload([
            'subject' => 'Formulaire contact',
            'body' => "formulaire 500\n\ncaladair.com",
            'from' => 'herve.nuzzo@caladair.com',
            'signature' => 'Hervé Nuzzo',
            'attachments' => [],
            'raw' => [],
        ]);
        $em->persist($ticket);
        $em->flush();

        $ticket->setStatus(TicketWorkflow::STATUS_AWAITING_FILE);
        $em->flush();

        $files->ensureTicketTree($number);
        $resultPath = 'analysis-results/run_probe_test.json';
        $files->write($number, $resultPath, json_encode([
            'schema_version' => '2',
            'ticket_number' => $number,
            'phase' => 'triage',
            'resolution' => 'needs_investigation',
            'service_request' => [
                'service' => 'http_probe',
                'params' => [
                    'url' => 'https://caladair.com/',
                    'purpose' => 'homepage_links',
                ],
            ],
            'confidence' => 0.9,
            'summary' => 'Test sonde',
            'client_reply_draft' => 'Bonjour',
            'internal_note' => 'Test',
            'project_detection' => [
                'project_key' => 'caladair',
                'domain' => 'caladair.com',
                'confidence' => 0.9,
                'reason' => 'Test',
            ],
        ], JSON_THROW_ON_ERROR));

        /** @var TraceableMessageBus $bus */
        $bus = $container->get(MessageBusInterface::class);
        $bus->dispatch(new ProcessFileOutcomeMessage($number, $resultPath, 'triage'));

        self::assertTrue($files->exists($number, 'services/executions/run_1.json'));
        self::assertTrue($files->exists($number, ServiceExecutionRecorder::CONTEXT_PATH));

        $context = json_decode($files->read($number, ServiceExecutionRecorder::CONTEXT_PATH), true);
        self::assertIsArray($context);
        self::assertGreaterThanOrEqual(1, $context['execution_count'] ?? 0);

        $ticket = $container->get(TicketProcessingRepository::class)->findOneBy(['ticketNumber' => $number]);
        self::assertNotNull($ticket);
        self::assertGreaterThanOrEqual(1, $ticket->getLoopCount());

        $execution = json_decode($files->read($number, 'services/executions/run_1.json'), true);
        self::assertSame('http_probe', $execution['service'] ?? null);
        self::assertSame(200, $execution['data']['status_code'] ?? null);
    }
}
