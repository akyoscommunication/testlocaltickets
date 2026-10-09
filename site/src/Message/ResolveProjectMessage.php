<?php

namespace App\Message;

final class ResolveProjectMessage
{
    public function __construct(
        public readonly string $ticketNumber,
        /** @var array<string, mixed> */
        public readonly array $analysisResult,
    ) {
    }
}
