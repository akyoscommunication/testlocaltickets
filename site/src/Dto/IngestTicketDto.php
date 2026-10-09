<?php

namespace App\Dto;

final readonly class IngestTicketDto
{
    public function __construct(
        public string $ticketNumber,
        public string $inputSource,
        public string $subject,
        public string $body,
        public ?string $sourceRef = null,
        public ?string $targetNodeId = null,
        public array $rawPayload = [],
        public array $attachments = [],
    ) {
    }
}
