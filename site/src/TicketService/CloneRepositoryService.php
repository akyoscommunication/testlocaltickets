<?php

namespace App\TicketService;

use App\Message\ResolveProjectMessage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;

#[AutoconfigureTag('app.ticket_service')]
final class CloneRepositoryService implements TicketServiceInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::CLONE_REPOSITORY;
    }

    public function getLabel(): string
    {
        return 'Cloner le dépôt';
    }

    public function getDescription(): string
    {
        return 'Lance la résolution projet et le clone (project_key IA ou paramètre explicite).';
    }

    public function getCategory(): string
    {
        return 'code';
    }

    public function supportsRelaunch(): bool
    {
        return false;
    }

    public function execute(TicketServiceContext $context): TicketServiceResult
    {
        $analysis = $context->analysisResult;
        $projectKey = trim((string) ($context->params['project_key'] ?? ''));
        if ('' !== $projectKey) {
            $detection = is_array($analysis['project_detection'] ?? null) ? $analysis['project_detection'] : [];
            $detection['project_key'] = $projectKey;
            $analysis['project_detection'] = $detection;
        }

        $this->bus->dispatch(new ResolveProjectMessage($context->ticketNumber, $analysis));

        $key = $analysis['project_detection']['project_key'] ?? $projectKey ?: '?';

        return TicketServiceResult::ok(
            sprintf('Clone dépôt lancé (projet %s).', $key),
            ['project_key' => $key],
        );
    }
}
