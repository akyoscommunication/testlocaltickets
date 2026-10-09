<?php

namespace App\Twig\Components;

use App\FileStorage\TicketFileStorage;
use App\Repository\AgentRunRepository;
use App\Repository\TicketProcessingRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AgentRunTimelineLiveComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $ticketNumber = '';

    #[LiveProp(writable: true)]
    public ?string $expandedRunId = null;

    #[LiveProp]
    public ?string $expandedPrompt = null;

    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly AgentRunRepository $agentRuns,
        private readonly TicketFileStorage $files,
    ) {
    }

    /**
     * @return list<\App\Entity\AgentRun>
     */
    public function getRuns(): array
    {
        $ticket = $this->tickets->findOneBy(['ticketNumber' => $this->ticketNumber]);
        if (null === $ticket) {
            return [];
        }

        return $this->agentRuns->findByTicketOrdered($ticket);
    }

    #[LiveAction]
    public function togglePrompt(#[LiveArg] string $runId): void
    {
        if ($this->expandedRunId === $runId) {
            $this->expandedRunId = null;
            $this->expandedPrompt = null;

            return;
        }

        $this->expandedRunId = $runId;
        $this->expandedPrompt = null;

        foreach ($this->getRuns() as $run) {
            if ($run->getRunId() !== $runId) {
                continue;
            }
            $path = $run->getRenderedPromptPath();
            if ($path && $this->files->exists($this->ticketNumber, $path)) {
                $this->expandedPrompt = $this->files->read($this->ticketNumber, $path);
            }
        }
    }
}
