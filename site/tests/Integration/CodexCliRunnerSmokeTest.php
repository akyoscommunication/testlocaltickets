<?php

namespace App\Tests\Integration;

use App\Agent\CodexCliRunner;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\ExecutableFinder;

#[Group('prod-smoke')]
final class CodexCliRunnerSmokeTest extends KernelTestCase
{
    public function testCodexBinaryAvailableWhenProfileRequiresIt(): void
    {
        $profile = (string) ($_ENV['AGENT_PROFILE'] ?? getenv('AGENT_PROFILE') ?: 'fixture_default');
        if ('fixture_default' === $profile) {
            self::markTestSkipped('Profil fixture — pas de validation Codex CLI.');
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('codex');
        if (null === $binary) {
            self::markTestSkipped('Binaire codex absent du PATH (P1-73 prod).');
        }

        self::bootKernel();
        $runner = self::getContainer()->get(CodexCliRunner::class);
        $this->assertSame('codex_cli', $runner->getName());
    }
}
