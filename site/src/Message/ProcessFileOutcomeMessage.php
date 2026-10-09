<?php

namespace App\Message;

final class ProcessFileOutcomeMessage
{
    public function __construct(
        public readonly string $ticketNumber,
        public readonly string $resultRelativePath,
        public readonly string $phase,
    ) {
    }
}
