<?php

namespace App\Twig\Components;

use App\Repository\TicketProcessingRepository;
use App\Service\ProjectResolverService;
use App\Workflow\TicketWorkflow;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class TicketListLiveComponent
{
    use DefaultActionTrait;

    private const PER_PAGE = 20;

    #[LiveProp(writable: true)]
    public ?string $statusFilter = null;

    #[LiveProp(writable: true)]
    public ?string $sourceFilter = null;

    #[LiveProp(writable: true)]
    public ?string $projectFilter = null;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly TicketProcessingRepository $tickets,
        private readonly ProjectResolverService $projects,
    ) {
    }

    /**
     * @return list<\App\Entity\TicketProcessing>
     */
    public function getTickets(): array
    {
        return $this->tickets->findFiltered(
            $this->statusFilter,
            $this->sourceFilter,
            $this->projectFilter,
            $this->page,
            self::PER_PAGE,
        );
    }

    public function getTotalPages(): int
    {
        $total = $this->tickets->countFiltered(
            $this->statusFilter,
            $this->sourceFilter,
            $this->projectFilter,
        );

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    /** @return list<string> */
    public function getStatusChoices(): array
    {
        return TicketWorkflow::allStatuses();
    }

    /** @return list<string> */
    public function getProjectKeys(): array
    {
        return $this->projects->getProjectKeys();
    }

    #[LiveAction]
    public function resetFilters(): void
    {
        $this->statusFilter = null;
        $this->sourceFilter = null;
        $this->projectFilter = null;
        $this->page = 1;
    }

    #[LiveAction]
    public function previousPage(): void
    {
        $this->page = max(1, $this->page - 1);
    }

    #[LiveAction]
    public function nextPage(): void
    {
        $this->page = min($this->getTotalPages(), $this->page + 1);
    }

    #[LiveAction]
    public function goToPage(#[LiveArg] int $page): void
    {
        $this->page = max(1, min($this->getTotalPages(), $page));
    }
}
