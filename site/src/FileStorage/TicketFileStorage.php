<?php

namespace App\FileStorage;

use League\Flysystem\FilesystemOperator;

/**
 * Chemins logiques relatifs à {ticket_number}/ sur le stockage tickets.
 */
final class TicketFileStorage
{
    public function __construct(
        private readonly FilesystemOperator $ticketStorage,
    ) {
    }

    public function write(string $ticketNumber, string $relativePath, string $contents): void
    {
        $this->ticketStorage->write($this->path($ticketNumber, $relativePath), $contents);
    }

    public function read(string $ticketNumber, string $relativePath): string
    {
        return $this->ticketStorage->read($this->path($ticketNumber, $relativePath));
    }

    public function exists(string $ticketNumber, string $relativePath): bool
    {
        return $this->ticketStorage->fileExists($this->path($ticketNumber, $relativePath));
    }

    /**
     * @return list<string> chemins relatifs (sans le préfixe ticket), fichiers uniquement
     */
    public function listFiles(string $ticketNumber, bool $recursive = true): array
    {
        $prefix = trim($ticketNumber, '/');
        if (!$this->ticketStorage->directoryExists($prefix)) {
            return [];
        }

        $paths = [];
        foreach ($this->ticketStorage->listContents($prefix, $recursive) as $item) {
            if (!$item->isFile() || str_ends_with($item->path(), '/.gitkeep')) {
                continue;
            }

            $relative = ltrim(substr($item->path(), strlen($prefix)), '/');
            if ('' !== $relative) {
                $paths[] = $relative;
            }
        }

        sort($paths);

        return $paths;
    }

    public function ensureTicketTree(string $ticketNumber): void
    {
        foreach (['attachments', 'agent-runs', 'context-packs', 'analysis-requests', 'analysis-results', 'code-analysis-requests', 'code-results', 'code-workspaces', 'archives', 'errors', 'investigation/probes'] as $dir) {
            $path = $this->path($ticketNumber, $dir.'/'.'.gitkeep');
            if (!$this->ticketStorage->fileExists($path)) {
                $this->ticketStorage->write($path, '');
            }
        }
        if (!$this->ticketStorage->fileExists($this->path($ticketNumber, 'inbox.json'))) {
            $this->ticketStorage->write($this->path($ticketNumber, 'inbox.json'), '{}');
        }
    }

    private function path(string $ticketNumber, string $relativePath): string
    {
        return trim($ticketNumber, '/').'/'.ltrim($relativePath, '/');
    }
}
