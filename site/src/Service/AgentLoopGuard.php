<?php

namespace App\Service;

use App\Entity\TicketProcessing;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AgentLoopGuard
{
    /** @var list<string> */
    public const RELAUNCHING_SERVICES = [
        'http_probe',
        'http_probe_batch',
        'rerun_agent',
    ];

    public function __construct(
        #[Autowire('%app.max_agent_loops_per_ticket%')]
        private readonly int $maxLoops,
    ) {
    }

    public function getMaxLoops(): int
    {
        return $this->maxLoops;
    }

    public function isRelaunchingService(string $serviceKey): bool
    {
        return in_array($serviceKey, self::RELAUNCHING_SERVICES, true);
    }

    public function canRelaunch(TicketProcessing $ticket): bool
    {
        return $ticket->getLoopCount() < $this->maxLoops;
    }

    public function remainingLoops(TicketProcessing $ticket): int
    {
        return max(0, $this->maxLoops - $ticket->getLoopCount());
    }
}
