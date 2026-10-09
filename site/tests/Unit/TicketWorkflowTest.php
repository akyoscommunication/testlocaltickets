<?php

namespace App\Tests\Unit;

use App\Entity\TicketProcessing;
use App\Workflow\TicketWorkflow;
use PHPUnit\Framework\TestCase;

final class TicketWorkflowTest extends TestCase
{
    public function testRetryAgentAllowedFromWaitingForHuman(): void
    {
        $workflow = new TicketWorkflow();
        $ticket = new TicketProcessing('TKT-WF-001', 'webhook');
        $ticket->setStatus(TicketWorkflow::STATUS_WAITING_FOR_HUMAN);

        self::assertTrue($workflow->can($ticket, TicketWorkflow::TRANSITION_RETRY_AGENT));

        $workflow->apply($ticket, TicketWorkflow::TRANSITION_RETRY_AGENT);
        self::assertSame(TicketWorkflow::STATUS_AGENT_PENDING, $ticket->getStatus());
    }

    public function testStartAgentAllowedFromStored(): void
    {
        $workflow = new TicketWorkflow();
        $ticket = new TicketProcessing('TKT-WF-003', 'webhook');
        $ticket->setStatus(TicketWorkflow::STATUS_STORED);

        self::assertTrue($workflow->can($ticket, TicketWorkflow::TRANSITION_START_AGENT));

        $workflow->apply($ticket, TicketWorkflow::TRANSITION_START_AGENT);
        self::assertSame(TicketWorkflow::STATUS_AGENT_RUNNING, $ticket->getStatus());
    }

    public function testFinishNotAllowedFromStored(): void
    {
        $workflow = new TicketWorkflow();
        $ticket = new TicketProcessing('TKT-WF-002', 'webhook');
        $ticket->setStatus(TicketWorkflow::STATUS_STORED);

        self::assertFalse($workflow->can($ticket, TicketWorkflow::TRANSITION_FINISH));
    }
}
