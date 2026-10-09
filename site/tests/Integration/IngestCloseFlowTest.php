<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\IngestTicketMessage;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class IngestCloseFlowTest extends KernelTestCase
{
    public function testWebhookIngestClosesToFinished(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);

        $number = 'TKT-IT-WH-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Info',
            body: 'Demande simple sans bug',
            sourceRef: 'wh-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());
        $this->assertNotNull($ticket->getFinishedAt());
    }

    public function testImapIngestClosesToWaitingForHuman(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);

        $number = 'TKT-IT-IM-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'imap',
            subject: 'Admin',
            body: 'Configuration à vérifier',
            sourceRef: 'im-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_WAITING_FOR_HUMAN, $ticket->getStatus());
        $this->assertNull($ticket->getFinishedAt());
    }
}
