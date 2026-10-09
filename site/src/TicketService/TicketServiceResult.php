<?php

namespace App\TicketService;

final class TicketServiceResult
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        public readonly bool $ok,
        public readonly string $summary,
        public readonly array $data = [],
        public readonly bool $shouldRelaunchAgent = false,
        public readonly string $targetPhase = 'triage',
    ) {
    }

    public static function ok(string $summary, array $data = [], bool $relaunch = false, string $phase = 'triage'): self
    {
        return new self(true, $summary, $data, $relaunch, $phase);
    }

    public static function fail(string $summary, array $data = []): self
    {
        return new self(false, $summary, $data, false);
    }
}
