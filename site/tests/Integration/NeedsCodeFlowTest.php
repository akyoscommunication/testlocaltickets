<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\IngestTicketMessage;
use App\Repository\AgentRunRepository;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class NeedsCodeFlowTest extends KernelTestCase
{
    public function testBugTicketRunsTriageAndCode(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $tickets = self::getContainer()->get(TicketProcessingRepository::class);
        $runs = self::getContainer()->get(AgentRunRepository::class);

        $number = 'TKT-IT-CODE-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Bug critique',
            body: 'Erreur 500 sur la page accueil',
            sourceRef: 'code-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());
        $this->assertNotNull($ticket->getResolvedProjectKey());

        $agentRuns = $runs->findByTicketNumber($number);
        $phases = array_map(static fn ($r) => $r->getPhase(), $agentRuns);
        $this->assertGreaterThanOrEqual(2, \count($agentRuns), 'Attendu : runs triage + code');
        $this->assertContains('triage', $phases);
        $this->assertContains('code_analysis', $phases);
    }
}
