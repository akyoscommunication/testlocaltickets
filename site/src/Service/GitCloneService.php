<?php

namespace App\Service;

use Symfony\Component\Process\Process;

final class GitCloneService
{
    public function cloneShallow(string $repoUrl, string $targetDirectory, string $branch = 'main'): void
    {
        if (is_dir($targetDirectory)) {
            return;
        }

        $parent = dirname($targetDirectory);
        if (!is_dir($parent)) {
            mkdir($parent, 0775, true);
        }

        $process = new Process([
            'git', 'clone',
            '--depth', '1',
            '--branch', $branch,
            $repoUrl,
            $targetDirectory,
        ]);
        $process->setTimeout(600);
        $process->mustRun();
    }
}
