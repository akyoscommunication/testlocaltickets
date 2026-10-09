<?php

namespace App\TicketService;

use App\Agent\AgentOutcomeVocabulary;
use App\Service\HumanActionNotifier;
use App\Workflow\TicketWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.ticket_service')]
final class RequestHumanActionService implements TicketServiceInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HumanActionNotifier $notifier,
        private readonly TicketWorkflow $ticketWorkflow,
    ) {
    }

    public function getKey(): string
    {
        return TicketServiceKeys::REQUEST_HUMAN_ACTION;
    }

    public function getLabel(): string
    {
        return 'Demander une action humaine';
    }

    public function getDescription(): string
    {
        return 'Met le ticket en attente opérateur ; callback HTTP si webhook avec callback_url.';
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
        $humanAction = (string) ($context->params['human_action'] ?? '');
        if ('' === $humanAction || 'none' === $humanAction) {
            return TicketServiceResult::fail('Paramètre human_action requis.');
        }

        if (!in_array($humanAction, AgentOutcomeVocabulary::HUMAN_ACTIONS, true) || 'none' === $humanAction) {
            return TicketServiceResult::fail('human_action invalide : '.$humanAction);
        }

        $summary = (string) ($context->params['summary'] ?? $context->analysisResult['summary'] ?? '');

        $this->ticketWorkflow->apply($context->ticket, TicketWorkflow::TRANSITION_WAIT_HUMAN);
        $this->em->flush();

        $payload = $context->ticket->getPayload();
        $this->notifier->notify($context->ticket, $humanAction, $summary, $payload);

        return TicketServiceResult::ok(
            'Action humaine : '.AgentOutcomeVocabulary::humanActionLabel($humanAction),
            ['human_action' => $humanAction],
        );
    }
}
