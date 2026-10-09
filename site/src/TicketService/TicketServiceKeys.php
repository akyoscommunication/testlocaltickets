<?php

namespace App\TicketService;

final class TicketServiceKeys
{
    public const HTTP_PROBE = 'http_probe';
    public const HTTP_PROBE_BATCH = 'http_probe_batch';
    public const CLONE_REPOSITORY = 'clone_repository';
    public const REQUEST_HUMAN_ACTION = 'request_human_action';
    public const CLOSE_TICKET = 'close_ticket';
    public const RERUN_AGENT = 'rerun_agent';

    /** @return list<string> */
    public static function all(): array
    {
        return [
            self::HTTP_PROBE,
            self::HTTP_PROBE_BATCH,
            self::CLONE_REPOSITORY,
            self::REQUEST_HUMAN_ACTION,
            self::CLOSE_TICKET,
            self::RERUN_AGENT,
        ];
    }
}
