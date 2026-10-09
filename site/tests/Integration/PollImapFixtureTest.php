<?php

namespace App\Tests\Integration;

use App\Message\PollImapMessage;
use App\Repository\TicketProcessingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class PollImapFixtureTest extends KernelTestCase
{
    public function testPollImapMessageIngestsFixtureAndMovesFile(): void
    {
        self::bootKernel();
        $this->ensureFixtureImapMode();
        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $inboxDir = $projectDir.'/var/imap-inbox';
        $processedDir = $projectDir.'/var/imap-processed';

        if (!is_dir($inboxDir)) {
            mkdir($inboxDir, 0775, true);
        }
        if (!is_dir($processedDir)) {
            mkdir($processedDir, 0775, true);
        }

        $messageId = 'fixture-'.uniqid('', true);
        $ticketNumber = 'TKT-POLL-'.substr(uniqid(), -6);
        $fixtureName = 'poll-'.$messageId.'.json';
        $fixturePath = $inboxDir.'/'.$fixtureName;

        file_put_contents($fixturePath, json_encode([
            'message_id' => $messageId,
            'ticket_number' => $ticketNumber,
            'subject' => 'Poll fixture',
            'body' => 'Corps test poll IMAP',
            'from' => 'qa@example.com',
        ], JSON_THROW_ON_ERROR));

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new PollImapMessage());

        $ticket = self::getContainer()->get(TicketProcessingRepository::class)
            ->findOneBy(['ticketNumber' => $ticketNumber]);

        $this->assertNotNull($ticket);
        $this->assertSame('imap', $ticket->getInputSource());
        $this->assertSame($messageId, $ticket->getSourceRef());
        $this->assertFileDoesNotExist($fixturePath);
        $this->assertFileExists($processedDir.'/'.$fixtureName);
    }

    private function ensureFixtureImapMode(): void
    {
        $settings = self::getContainer()->get(\App\Service\AppSettingsService::class);
        $settings->save([
            'imap' => [
                'poll_enabled' => true,
                'use_fixture' => true,
            ],
        ]);
    }
}
