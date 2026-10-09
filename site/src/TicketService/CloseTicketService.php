<?php

namespace App\TicketService;

use App\Message\ExecuteCloseActionsMessage;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Messenger\MessageBusInterface;

#[AutoconfigureTag('app.ticket_service')]
final class CloseTicketService implements TicketServiceInterface
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::CLOSE_TICKET;
    }

    public function getLabel(): string
    {
        return 'Clôturer le ticket';
    }

    public function getDescription(): string
    {
        return 'Applique la clôture (réponse client, statut final selon source).';
    }

    public function getCategory(): string
    {
        return 'workflow';
    }

    public function supportsRelaunch(): bool
    {
        return false;
    }

    public function execute(TicketServiceContext $context): TicketServiceResult
    {
        $this->bus->dispatch(new ExecuteCloseActionsMessage(
            $context->ticketNumber,
            $context->analysisResult,
        ));

        return TicketServiceResult::ok('Clôture du ticket lancée.');
    }
}
