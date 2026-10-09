<?php

namespace App\Agent;

use App\Service\AppSettingsService;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Process\Process;

final class CodexCliRunner implements AgentRunnerInterface
{
    public function __construct(
        private readonly AppSettingsService $settings,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getName(): string
    {
        return 'codex_cli';
    }

    public function run(AgentRunRequest $request): AgentRunResult
    {
        $binary = $this->resolveBinary();
        $stderrFile = $request->agentRunDir.'/stderr.log';

        $process = new Process([
            $binary,
            'run',
            '--request', $request->requestFilePath,
            '--prompt', $request->promptFilePath,
            '--output', $request->resultFilePath,
        ]);
        $process->setTimeout($request->timeoutSeconds);
        $process->run();

        file_put_contents($stderrFile, $process->getErrorOutput());

        if (!$process->isSuccessful() || !is_file($request->resultFilePath)) {
            $stderr = AgentRunErrorFormatter::collectStderr($request, $process->getErrorOutput());
            $exitCode = $process->getExitCode() ?? 1;

            return new AgentRunResult(
                false,
                $stderr,
                $exitCode,
                AgentRunErrorFormatter::summarize($stderr, $exitCode),
            );
        }

        if (is_dir($request->agentRunDir)) {
            copy($request->resultFilePath, $request->agentRunDir.'/response.json');
        }

        return new AgentRunResult(true);
    }

    public function resolveBinary(): string
    {
        $configured = $this->settings->getAgentBinary();
        if ('' !== $configured && is_executable($configured)) {
            return $configured;
        }

        $fallback = $this->projectDir.'/bin/codex-ticket-run';
        if (is_executable($fallback)) {
            return $fallback;
        }

        return $configured ?: $fallback;
    }
}
