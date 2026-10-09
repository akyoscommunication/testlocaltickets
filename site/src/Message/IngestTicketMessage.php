<?php

namespace App\Message;

use App\Dto\IngestTicketDto;

final class IngestTicketMessage
{
    public function __construct(
        public readonly IngestTicketDto $dto,
    ) {
    }
}
