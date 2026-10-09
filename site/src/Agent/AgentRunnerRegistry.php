<?php

namespace App\Agent;

use App\Service\AppSettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AgentRunnerRegistry
{
    public function __construct(
        private readonly FixtureAgentRunner $fixtureRunner,
        private readonly CodexCliRunner $codexRunner,
        private readonly AppSettingsService $settings,
        #[Autowire(param: 'agents')]
        private readonly array $agentsConfig,
    ) {
    }

    public function getActiveRunner(): AgentRunnerInterface
    {
        $profile = $this->settings->getAgentProfile();
        $profileConfig = $this->agentsConfig[$profile] ?? null;
        if (null === $profileConfig) {
            return $this->fixtureRunner;
        }

        return match ($profileConfig['runner'] ?? 'fixture') {
            'codex_cli' => $this->codexRunner,
            'fixture' => $this->fixtureRunner,
            default => $this->fixtureRunner,
        };
    }

    public function getActiveProfile(): string
    {
        return $this->settings->getAgentProfile();
    }
}
