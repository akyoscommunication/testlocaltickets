<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\IngestTicketMessage;
use App\Message\RunTriageAgentMessage;
use App\Repository\AgentRunRepository;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class AgentErrorRetryTest extends KernelTestCase
{
    public function testAgentErrorThenRetrySucceeds(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);
        $runs = self::getContainer()->get(AgentRunRepository::class);
        $em = self::getContainer()->get('doctrine')->getManager();

        $number = 'TKT-ERR-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Test échec',
            body: 'Demande simple __agent_fail__',
            sourceRef: 'err-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_AGENT_ERROR, $ticket->getStatus());

        $failedRuns = $runs->findByTicketNumber($number);
        $this->assertCount(1, $failedRuns);
        $this->assertSame('failed', $failedRuns[0]->getStatus());
        $firstRunId = $failedRuns[0]->getRunId();

        $payload = $ticket->getPayload();
        $payload['body'] = 'Demande simple sans échec';
        $ticket->setPayload($payload);
        $ticket->setStatus(TicketWorkflow::STATUS_AGENT_PENDING);
        $em->flush();

        $bus->dispatch(new RunTriageAgentMessage($number));

        $em->refresh($ticket);
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());

        $allRuns = $runs->findByTicketNumber($number);
        $this->assertCount(2, $allRuns);
        $this->assertNotSame($firstRunId, $allRuns[1]->getRunId());
        $this->assertSame('completed', $allRuns[1]->getStatus());
    }
}
