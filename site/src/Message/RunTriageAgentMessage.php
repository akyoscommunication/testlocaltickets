<?php

namespace App\Message;

final class RunTriageAgentMessage
{
    public function __construct(
        public readonly string $ticketNumber,
    ) {
    }
}
