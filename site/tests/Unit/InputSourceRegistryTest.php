<?php

namespace App\Tests\Unit;

use App\Input\InputSourceInterface;
use App\Input\InputSourceRegistry;
use PHPUnit\Framework\TestCase;

final class InputSourceRegistryTest extends TestCase
{
    public function testRegistryListsSourcesAndEnabledFlag(): void
    {
        $imap = $this->createStub(InputSourceInterface::class);
        $imap->method('getKey')->willReturn('imap');
        $webhook = $this->createStub(InputSourceInterface::class);
        $webhook->method('getKey')->willReturn('webhook');

        $registry = new InputSourceRegistry(
            [$imap, $webhook],
            [
                'imap' => ['enabled' => true],
                'webhook' => ['enabled' => false],
            ],
        );

        $this->assertSame($imap, $registry->get('imap'));
        $this->assertTrue($registry->isEnabled('imap'));
        $this->assertFalse($registry->isEnabled('webhook'));
        $this->assertCount(1, $registry->enabled());
    }

    public function testUnknownSourceThrows(): void
    {
        $registry = new InputSourceRegistry([], []);

        $this->expectException(\InvalidArgumentException::class);
        $registry->get('unknown');
    }
}
