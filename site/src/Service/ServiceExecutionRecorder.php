<?php

namespace App\Service;

use App\FileStorage\TicketFileStorage;

/**
 * Journalise les exécutions de services ticket (services/executions/) et agrège le contexte IA.
 */
final class ServiceExecutionRecorder
{
    public const CONTEXT_PATH = 'services/context.json';
    private const EXECUTION_PREFIX = 'services/executions/run_';

    public function __construct(
        private readonly TicketFileStorage $files,
    ) {
    }

    public function nextExecutionPath(string $ticketNumber): string
    {
        $existing = array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $p): bool => str_starts_with($p, self::EXECUTION_PREFIX) && str_ends_with($p, '.json'),
        );

        return sprintf('%s%d.json', self::EXECUTION_PREFIX, count($existing) + 1);
    }

    public function countExecutions(string $ticketNumber): int
    {
        return count(array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $p): bool => str_starts_with($p, self::EXECUTION_PREFIX) && str_ends_with($p, '.json'),
        ));
    }

    /**
     * @param array<string, mixed> $execution
     */
    public function record(string $ticketNumber, string $relativePath, array $execution): void
    {
        $payload = array_merge([
            'executed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], $execution);

        $this->files->write(
            $ticketNumber,
            $relativePath,
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        $this->rebuildContext($ticketNumber);
    }

    public function rebuildContext(string $ticketNumber): void
    {
        $executions = $this->loadExecutions($ticketNumber);
        $legacyProbes = $this->loadLegacyProbes($ticketNumber);

        $lines = [];
        foreach ($legacyProbes as $probe) {
            $status = $probe['status_code'] ?? '?';
            $url = $probe['final_url'] ?? $probe['url'] ?? '?';
            $lines[] = sprintf('[legacy] GET %s → HTTP %s', $url, $status);
        }

        foreach ($executions as $execution) {
            $service = (string) ($execution['service'] ?? '?');
            $summary = (string) ($execution['summary'] ?? '');
            $lines[] = sprintf('[%s] %s', $service, $summary);
            foreach ($execution['summary_lines'] ?? [] as $line) {
                if (is_string($line) && '' !== $line) {
                    $lines[] = '  '.$line;
                }
            }
        }

        $context = [
            'schema_version' => '1',
            'execution_count' => count($executions),
            'legacy_probe_count' => count($legacyProbes),
            'summary_lines' => $lines,
            'executions' => $executions,
            'legacy_probes' => $legacyProbes,
        ];

        $this->files->write(
            $ticketNumber,
            self::CONTEXT_PATH,
            json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function readContext(string $ticketNumber): ?array
    {
        if ($this->files->exists($ticketNumber, self::CONTEXT_PATH)) {
            $data = json_decode($this->files->read($ticketNumber, self::CONTEXT_PATH), true);

            return is_array($data) ? $data : null;
        }

        if ($this->hasLegacyProbes($ticketNumber)) {
            $this->rebuildContext($ticketNumber);

            return $this->readContext($ticketNumber);
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listExecutions(string $ticketNumber): array
    {
        return $this->loadExecutions($ticketNumber);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadExecutions(string $ticketNumber): array
    {
        $paths = array_values(array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $p): bool => str_starts_with($p, self::EXECUTION_PREFIX) && str_ends_with($p, '.json'),
        ));
        sort($paths);

        $executions = [];
        foreach ($paths as $path) {
            $data = json_decode($this->files->read($ticketNumber, $path), true);
            if (is_array($data)) {
                $data['_path'] = $path;
                $executions[] = $data;
            }
        }

        return $executions;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadLegacyProbes(string $ticketNumber): array
    {
        $paths = array_values(array_filter(
            $this->files->listFiles($ticketNumber),
            static fn (string $p): bool => str_starts_with($p, 'investigation/probes/probe_') && str_ends_with($p, '.json'),
        ));
        sort($paths);

        $probes = [];
        foreach ($paths as $path) {
            $data = json_decode($this->files->read($ticketNumber, $path), true);
            if (is_array($data)) {
                $probes[] = $data;
            }
        }

        return $probes;
    }

    private function hasLegacyProbes(string $ticketNumber): bool
    {
        foreach ($this->files->listFiles($ticketNumber) as $path) {
            if (str_starts_with($path, 'investigation/probes/probe_')) {
                return true;
            }
        }

        return false;
    }
}
