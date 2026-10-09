<?php

namespace App\Agent;

final readonly class AgentRunRequest
{
    public function __construct(
        public string $ticketNumber,
        public string $runId,
        public string $phase,
        public string $requestFilePath,
        public string $promptFilePath,
        public string $resultFilePath,
        public string $agentRunDir,
        public int $timeoutSeconds,
    ) {
    }
}
