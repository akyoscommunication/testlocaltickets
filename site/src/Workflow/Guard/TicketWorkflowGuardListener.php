<?php

namespace App\Workflow\Guard;

use App\Entity\TicketProcessing;
use App\Service\AgentLoopGuard;
use App\Workflow\TicketWorkflow;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\GuardEvent;

final class TicketWorkflowGuardListener
{
    public function __construct(
        private readonly AgentLoopGuard $loopGuard,
    ) {
    }

    #[AsEventListener(event: 'workflow.ticket_processing.guard.retry_agent')]
    public function guardRetryAgent(GuardEvent $event): void
    {
        $ticket = $event->getSubject();
        if (!$ticket instanceof TicketProcessing) {
            return;
        }

        if (!$this->loopGuard->canRelaunch($ticket)) {
            $event->setBlocked(true, sprintf(
                'Plafond boucles IA atteint (%d).',
                $this->loopGuard->getMaxLoops(),
            ));
        }
    }
}
