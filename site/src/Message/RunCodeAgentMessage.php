<?php

namespace App\Message;

final class RunCodeAgentMessage
{
    public function __construct(
        public readonly string $ticketNumber,
        public readonly string $contextPackPath,
    ) {
    }
}
