<?php

namespace App\Workflow;

use App\Entity\TicketProcessing;
use LeTots\WorkflowExtension\AbstractWorkflow;
use LeTots\WorkflowExtension\Attribute\AsWorkflow;
use LeTots\WorkflowExtension\Attribute\Place;
use LeTots\WorkflowExtension\Attribute\Transition;

#[AsWorkflow(
    name: 'ticket_processing',
    type: AsWorkflow::TYPE_STATE_MACHINE,
    supportStrategy: TicketProcessing::class,
)]
class TicketWorkflow extends AbstractWorkflow
{
    #[Place(initial: true)]
    public const string STATUS_RECEIVED = 'received';

    #[Place]
    public const string STATUS_STORED = 'stored';

    #[Place]
    public const string STATUS_AGENT_PENDING = 'agent_pending';

    #[Place]
    public const string STATUS_AGENT_RUNNING = 'agent_running';

    #[Place]
    public const string STATUS_AWAITING_FILE = 'awaiting_file';

    #[Place]
    public const string STATUS_PROCESSING_OUTCOME = 'processing_outcome';

    #[Place]
    public const string STATUS_EXECUTING_ACTIONS = 'executing_actions';

    #[Place]
    public const string STATUS_WAITING_FOR_HUMAN = 'waiting_for_human';

    #[Place]
    public const string STATUS_FINISHED = 'finished';

    #[Place]
    public const string STATUS_FAILED = 'failed';

    #[Place]
    public const string STATUS_AGENT_ERROR = 'agent_error';

    #[Place]
    public const string STATUS_IGNORED = 'ignored';

    #[Transition(from: self::STATUS_RECEIVED, to: self::STATUS_STORED)]
    public const string TRANSITION_STORE = 'store';

    public const string TRANSITION_START_AGENT = 'start_agent';

    #[Transition(from: self::STATUS_STORED, to: self::STATUS_AGENT_RUNNING)]
    public const string TRANSITION_START_AGENT_FROM_STORED = 'start_agent';

    #[Transition(from: self::STATUS_AGENT_PENDING, to: self::STATUS_AGENT_RUNNING)]
    public const string TRANSITION_START_AGENT_FROM_PENDING = 'start_agent';

    #[Transition(from: self::STATUS_EXECUTING_ACTIONS, to: self::STATUS_AGENT_RUNNING)]
    public const string TRANSITION_START_AGENT_FROM_EXECUTING = 'start_agent';

    #[Transition(from: self::STATUS_AGENT_RUNNING, to: self::STATUS_AWAITING_FILE)]
    public const string TRANSITION_AGENT_COMPLETED = 'agent_completed';

    #[Transition(from: self::STATUS_AGENT_RUNNING, to: self::STATUS_AGENT_ERROR)]
    public const string TRANSITION_AGENT_FAILED = 'agent_failed';

    #[Transition(from: self::STATUS_AWAITING_FILE, to: self::STATUS_PROCESSING_OUTCOME)]
    public const string TRANSITION_PROCESS_OUTCOME = 'process_outcome';

    #[Transition(from: self::STATUS_PROCESSING_OUTCOME, to: self::STATUS_EXECUTING_ACTIONS)]
    public const string TRANSITION_EXECUTE_ACTIONS = 'execute_actions';

    public const string TRANSITION_QUEUE_AGENT = 'queue_agent';

    #[Transition(from: self::STATUS_PROCESSING_OUTCOME, to: self::STATUS_AGENT_PENDING)]
    public const string TRANSITION_QUEUE_AGENT_FROM_OUTCOME = 'queue_agent';

    #[Transition(from: self::STATUS_EXECUTING_ACTIONS, to: self::STATUS_AGENT_PENDING)]
    public const string TRANSITION_QUEUE_AGENT_FROM_EXECUTING = 'queue_agent';

    #[Transition(from: self::STATUS_PROCESSING_OUTCOME, to: self::STATUS_WAITING_FOR_HUMAN)]
    public const string TRANSITION_WAIT_HUMAN = 'wait_human';

    public const string TRANSITION_FINISH = 'finish';

    #[Transition(from: self::STATUS_PROCESSING_OUTCOME, to: self::STATUS_FINISHED)]
    public const string TRANSITION_FINISH_FROM_OUTCOME = 'finish';

    #[Transition(from: self::STATUS_WAITING_FOR_HUMAN, to: self::STATUS_FINISHED)]
    public const string TRANSITION_FINISH_FROM_WAITING = 'finish';

    #[Transition(from: self::STATUS_EXECUTING_ACTIONS, to: self::STATUS_FAILED)]
    public const string TRANSITION_CLONE_FAILED = 'clone_failed';

    public const string TRANSITION_RETRY_AGENT = 'retry_agent';

    #[Transition(from: self::STATUS_AGENT_ERROR, to: self::STATUS_AGENT_PENDING)]
    public const string TRANSITION_RETRY_AGENT_FROM_ERROR = 'retry_agent';

    #[Transition(from: self::STATUS_FAILED, to: self::STATUS_AGENT_PENDING)]
    public const string TRANSITION_RETRY_AGENT_FROM_FAILED = 'retry_agent';

    #[Transition(from: self::STATUS_WAITING_FOR_HUMAN, to: self::STATUS_AGENT_PENDING)]
    public const string TRANSITION_RETRY_AGENT_FROM_WAITING = 'retry_agent';

    #[Transition(from: self::STATUS_WAITING_FOR_HUMAN, to: self::STATUS_IGNORED)]
    public const string TRANSITION_IGNORE = 'ignore';

    /** @return list<string> */
    public static function allStatuses(): array
    {
        return [
            self::STATUS_RECEIVED,
            self::STATUS_STORED,
            self::STATUS_AGENT_PENDING,
            self::STATUS_AGENT_RUNNING,
            self::STATUS_AWAITING_FILE,
            self::STATUS_PROCESSING_OUTCOME,
            self::STATUS_EXECUTING_ACTIONS,
            self::STATUS_WAITING_FOR_HUMAN,
            self::STATUS_FINISHED,
            self::STATUS_FAILED,
            self::STATUS_AGENT_ERROR,
            self::STATUS_IGNORED,
        ];
    }
}
