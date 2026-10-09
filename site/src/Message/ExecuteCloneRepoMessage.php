<?php

namespace App\Message;

final class ExecuteCloneRepoMessage
{
    public function __construct(
        public readonly string $ticketNumber,
        public readonly string $projectKey,
    ) {
    }
}
