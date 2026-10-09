<?php

namespace App\TicketService;

interface TicketServiceInterface
{
    public function getKey(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    public function getCategory(): string;

    public function supportsRelaunch(): bool;

    public function execute(TicketServiceContext $context): TicketServiceResult;
}
