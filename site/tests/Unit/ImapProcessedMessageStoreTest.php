<?php

namespace App\Tests\Unit;

use App\Service\ImapProcessedMessageStore;
use PHPUnit\Framework\TestCase;

final class ImapProcessedMessageStoreTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir().'/ticket-imap-store-'.uniqid('', true);
        mkdir($this->projectDir.'/var/state', 0775, true);
    }

    protected function tearDown(): void
    {
        $stateDir = $this->projectDir.'/var/state';
        if (is_dir($stateDir)) {
            array_map(unlink(...), glob($stateDir.'/*') ?: []);
            rmdir($stateDir);
            rmdir($this->projectDir.'/var');
            rmdir($this->projectDir);
        }
    }

    public function testMarkAndHas(): void
    {
        $store = new ImapProcessedMessageStore($this->projectDir);

        $this->assertFalse($store->has('<abc@example.com>'));
        $store->mark('<abc@example.com>');
        $this->assertTrue($store->has('<abc@example.com>'));
    }
}
