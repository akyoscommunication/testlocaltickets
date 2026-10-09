<?php

namespace App\TicketService;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class TicketServiceRegistry
{
    /** @var array<string, TicketServiceInterface> */
    private array $services = [];

    /**
     * @param iterable<TicketServiceInterface> $services
     */
    public function __construct(
        #[AutowireIterator('app.ticket_service')]
        iterable $services,
    ) {
        foreach ($services as $service) {
            $this->services[$service->getKey()] = $service;
        }
    }

    public function get(string $key): TicketServiceInterface
    {
        if (!isset($this->services[$key])) {
            throw new \InvalidArgumentException(sprintf('Service ticket inconnu : %s', $key));
        }

        return $this->services[$key];
    }

    public function has(string $key): bool
    {
        return isset($this->services[$key]);
    }

    /** @return list<TicketServiceInterface> */
    public function all(): array
    {
        return array_values($this->services);
    }
}
