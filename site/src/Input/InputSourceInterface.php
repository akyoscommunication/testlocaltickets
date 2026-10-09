<?php

namespace App\Input;

use App\Dto\IngestTicketDto;

interface InputSourceInterface
{
    public function getKey(): string;

    /**
     * @return list<IngestTicketDto>
     */
    public function poll(): array;
}
