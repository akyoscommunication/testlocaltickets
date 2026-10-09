<?php

namespace App\Monolog;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Normalise ticket_number et run_id dans le contexte des logs ticket_agent.
 */
final class TicketContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $extra = $record->extra;

        if (isset($context['ticket_number'])) {
            $extra['ticket_number'] = $context['ticket_number'];
        }
        if (isset($context['run_id'])) {
            $extra['run_id'] = $context['run_id'];
        }

        return $record->with(extra: $extra);
    }
}
