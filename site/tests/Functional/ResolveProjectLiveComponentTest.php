<?php

namespace App\Tests\Functional;

use App\Dto\IngestTicketDto;
use App\Workflow\TicketWorkflow;
use App\Message\IngestTicketMessage;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;

final class ResolveProjectLiveComponentTest extends WebTestCase
{
    use InteractsWithLiveComponents;

    public function testSubmitResolveProjectFormClonesAndFinishes(): void
    {
        $client = static::createClient();
        $bus = static::getContainer()->get(MessageBusInterface::class);
        $tickets = static::getContainer()->get(TicketProcessingRepository::class);

        $number = 'TKT-LC-'.substr(uniqid(), -6);
        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'Bug multi-sites',
            body: 'Erreur 500 sur client.fr et b2b.client.fr',
            sourceRef: 'lc-'.uniqid(),
        )));

        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame(TicketWorkflow::STATUS_WAITING_FOR_HUMAN, $ticket->getStatus());
        $this->assertNull($ticket->getResolvedProjectKey());

        $testComponent = $this->createLiveComponent(
            name: 'ResolveProjectLiveComponent',
            data: ['ticketNumber' => $number],
            client: $client,
        );

        $liveComponent = $testComponent->component();
        $this->assertTrue($liveComponent->isVisible());

        $formName = $liveComponent->getFormName();
        $testComponent->submitForm(
            [$formName => ['project_key' => 'client-b2b']],
            'validateAndClone',
        );

        static::getContainer()->get('doctrine')->getManager()->clear();
        $ticket = $tickets->findOneBy(['ticketNumber' => $number]);
        $this->assertNotNull($ticket);
        $this->assertSame('client-b2b', $ticket->getResolvedProjectKey());
        $this->assertSame('manual', $ticket->getRepoResolution());
        $this->assertSame(TicketWorkflow::STATUS_FINISHED, $ticket->getStatus());
    }
}
