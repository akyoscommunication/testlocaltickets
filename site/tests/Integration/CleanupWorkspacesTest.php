<?php

namespace App\Tests\Integration;

use App\Entity\TicketProcessing;
use App\Workflow\TicketWorkflow;
use App\Message\CleanupWorkspacesMessage;
use App\Repository\TicketProcessingRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class CleanupWorkspacesTest extends KernelTestCase
{
    public function testRemovesOldWorkspace(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get('doctrine')->getManager();
        $storage = self::getContainer()->get('ticket.storage');
        \assert($storage instanceof FilesystemOperator);
        $bus = self::getContainer()->get(MessageBusInterface::class);

        $number = 'TKT-CLEAN-'.substr(uniqid(), -6);
        $ticket = new TicketProcessing($number, 'webhook');
        $ticket->setStatus(TicketWorkflow::STATUS_FINISHED);
        $ticket->setResolvedProjectKey('client-site');
        $ticket->setFinishedAt(new \DateTimeImmutable('-20 days'));
        $em->persist($ticket);
        $em->flush();

        $workspace = $number.'/code-workspaces/client-site';
        $storage->write($workspace.'/.gitkeep', '');
        $this->assertTrue($storage->directoryExists($workspace));

        $bus->dispatch(new CleanupWorkspacesMessage());

        $this->assertFalse($storage->directoryExists($workspace));

        $em->remove($ticket);
        $em->flush();
    }
}
