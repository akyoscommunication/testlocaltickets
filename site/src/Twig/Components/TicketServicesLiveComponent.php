<?php

namespace App\Twig\Components;

use App\Service\ServiceExecutionRecorder;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class TicketServicesLiveComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $ticketNumber = '';

    public function __construct(
        private readonly ServiceExecutionRecorder $recorder,
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function getExecutions(): array
    {
        if ('' === $this->ticketNumber) {
            return [];
        }

        return $this->recorder->listExecutions($this->ticketNumber);
    }

    /** @return array<string, mixed>|null */
    public function getContext(): ?array
    {
        if ('' === $this->ticketNumber) {
            return null;
        }

        return $this->recorder->readContext($this->ticketNumber);
    }
}
