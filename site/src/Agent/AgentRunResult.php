<?php

namespace App\Agent;

final readonly class AgentRunResult
{
    public function __construct(
        public bool $success,
        public ?string $stderr = null,
        public int $exitCode = 0,
        public ?string $errorSummary = null,
    ) {
    }
}
