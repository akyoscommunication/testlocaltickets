<?php

namespace App\Twig\Components;

use App\Repository\AutomationLogRepository;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AutomationLogLiveComponent
{
    use DefaultActionTrait;

    private const PER_PAGE = 20;

    #[LiveProp(writable: true, onUpdated: 'resetPage')]
    public ?string $ticketFilter = null;

    #[LiveProp(writable: true)]
    public int $page = 1;

    public function __construct(
        private readonly AutomationLogRepository $logs,
    ) {
    }

    /**
     * @return list<\App\Entity\AutomationLog>
     */
    public function getLogs(): array
    {
        $filter = '' === $this->ticketFilter ? null : $this->ticketFilter;

        return $this->logs->findPaginated($filter, $this->page, self::PER_PAGE);
    }

    public function getTotalPages(): int
    {
        $filter = '' === $this->ticketFilter ? null : $this->ticketFilter;
        $total = $this->logs->countFiltered($filter);

        return max(1, (int) ceil($total / self::PER_PAGE));
    }

    public function getTotalCount(): int
    {
        $filter = '' === $this->ticketFilter ? null : $this->ticketFilter;

        return $this->logs->countFiltered($filter);
    }

    public function resetPage(): void
    {
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
