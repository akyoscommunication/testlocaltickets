<?php

namespace App\Tests\Integration;

use App\Input\ImapInputSource;
use App\Service\AppSettingsService;
use App\Service\ImapProcessedMessageStore;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ImapProcessedRegistryTest extends KernelTestCase
{
    public function testSecondPollSkipsMessageIdAlreadyInRegistry(): void
    {
        self::bootKernel();
        self::getContainer()->get(AppSettingsService::class)->save([
            'imap' => [
                'poll_enabled' => true,
                'use_fixture' => true,
            ],
        ]);

        $projectDir = self::getContainer()->getParameter('kernel.project_dir');
        $registryPath = $projectDir.'/var/state/imap-processed-ids.json';
        if (is_file($registryPath)) {
            unlink($registryPath);
        }

        $inboxDir = $projectDir.'/var/imap-inbox';
        if (!is_dir($inboxDir)) {
            mkdir($inboxDir, 0775, true);
        }
        foreach (glob($inboxDir.'/*.json') ?: [] as $stale) {
            unlink($stale);
        }

        $messageId = 'fixture-registry-'.uniqid('', true);
        $fixturePath = $inboxDir.'/registry-'.$messageId.'.json';

        file_put_contents($fixturePath, json_encode([
            'message_id' => $messageId,
            'ticket_number' => 'TKT-REG-'.substr(uniqid(), -6),
            'subject' => 'Registry test',
            'body' => 'Corps',
            'from' => 'qa@example.com',
        ], JSON_THROW_ON_ERROR));

        $imap = self::getContainer()->get(ImapInputSource::class);
        $store = self::getContainer()->get(ImapProcessedMessageStore::class);

        $first = $imap->poll();
        $this->assertCount(1, $first);
        $this->assertTrue($store->has($messageId));

        file_put_contents($fixturePath, json_encode([
            'message_id' => $messageId,
            'ticket_number' => 'TKT-REG-SHOULD-NOT',
            'subject' => 'Duplicate',
            'body' => 'Corps',
            'from' => 'qa@example.com',
        ], JSON_THROW_ON_ERROR));

        $second = $imap->poll();
        $this->assertSame([], $second);
    }
}
