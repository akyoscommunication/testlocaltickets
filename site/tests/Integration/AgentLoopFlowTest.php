<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\IngestTicketMessage;
use App\Repository\AgentRunRepository;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class AgentLoopFlowTest extends KernelTestCase
{
    public function testAgentLoopRelaunchesCodeThenCloses(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);
        $runs = self::getContainer()->get(AgentRunRepository::class);

        $number = 'TKT-IT-LOOP-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Bug boucle agent',
            body: 'Erreur 500 __agent_loop__ sur la prod',
            sourceRef: 'loop-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());
        $this->assertSame(1, $ticket->getLoopCount());

        $codeRuns = array_filter(
            $runs->findByTicketNumber($number),
            static fn ($r) => 'code_analysis' === $r->getPhase(),
        );
        $this->assertGreaterThanOrEqual(2, \count($codeRuns), 'agent_loop doit relancer code_analysis');
    }
}
