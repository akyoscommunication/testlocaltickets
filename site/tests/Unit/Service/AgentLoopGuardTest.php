<?php

namespace App\Tests\Unit\Service;

use App\Entity\TicketProcessing;
use App\Service\AgentLoopGuard;
use PHPUnit\Framework\TestCase;

final class AgentLoopGuardTest extends TestCase
{
    public function testMaxLoopsAndRelaunchingServices(): void
    {
        $guard = new AgentLoopGuard(10);
        $ticket = new TicketProcessing('TKT-1', 'webhook');

        self::assertTrue($guard->canRelaunch($ticket));
        self::assertSame(10, $guard->remainingLoops($ticket));
        self::assertTrue($guard->isRelaunchingService('http_probe'));
        self::assertTrue($guard->isRelaunchingService('http_probe_batch'));
        self::assertFalse($guard->isRelaunchingService('close_ticket'));

        $ticket->setLoopCount(10);
        self::assertFalse($guard->canRelaunch($ticket));
        self::assertSame(0, $guard->remainingLoops($ticket));
    }
}
