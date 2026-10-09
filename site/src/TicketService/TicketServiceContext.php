<?php

namespace App\TicketService;

use App\Entity\TicketProcessing;

final class TicketServiceContext
{
    /**
     * @param array<string, mixed>        $analysisResult
     * @param array<string, mixed>        $params
     * @param list<string>                $probeAllowlist
     */
    public function __construct(
        public readonly TicketProcessing $ticket,
        public readonly string $ticketNumber,
        public readonly string $phase,
        public readonly array $analysisResult,
        public readonly array $params,
        public readonly array $probeAllowlist,
    ) {
    }
}
