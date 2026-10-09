<?php

namespace App;

use App\Message\CleanupWorkspacesMessage;
use App\Message\PollImapMessage;
use App\Message\WatchPendingFilesMessage;
use App\Service\AppSettingsService;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule]
class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly AppSettingsService $settings,
    ) {
    }

    public function getSchedule(): SymfonySchedule
    {
        $schedule = (new SymfonySchedule())
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every('1 minute', new WatchPendingFilesMessage()))
            ->add(RecurringMessage::every('1 day', new CleanupWorkspacesMessage()));

        if ($this->settings->isImapPollEnabled()) {
            $schedule->add(RecurringMessage::every('2 minutes', new PollImapMessage()));
        }

        return $schedule;
    }
}
