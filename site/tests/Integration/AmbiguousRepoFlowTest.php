<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\ExecuteCloneRepoMessage;
use App\Message\IngestTicketMessage;
use App\Repository\AgentRunRepository;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class AmbiguousRepoFlowTest extends KernelTestCase
{
    public function testAmbiguousResolutionThenManualClone(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);
        $runs = self::getContainer()->get(AgentRunRepository::class);
        $em = self::getContainer()->get('doctrine')->getManager();

        $number = 'TKT-AMB-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Bug multi-sites',
            body: 'Erreur 500 sur client.fr et b2b.client.fr',
            sourceRef: 'amb-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_WAITING_FOR_HUMAN, $ticket->getStatus());
        $this->assertNull($ticket->getResolvedProjectKey());
        $this->assertContains($runs->findByTicketNumber($number)[0]->getPhase(), ['triage']);

        $ticket->setResolvedProjectKey('client-b2b');
        $ticket->setRepoResolution('manual');
        $em->flush();

        $bus->dispatch(new ExecuteCloneRepoMessage($number, 'client-b2b'));

        $em->refresh($ticket);
        $this->assertSame('client-b2b', $ticket->getResolvedProjectKey());
        $this->assertSame('manual', $ticket->getRepoResolution());
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());

        $phases = array_map(static fn ($r) => $r->getPhase(), $runs->findByTicketNumber($number));
        $this->assertContains('triage', $phases);
        $this->assertContains('code_analysis', $phases);
    }
}
