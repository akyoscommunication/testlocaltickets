<?php

namespace App\Tests\Integration;

use App\Service\GitCloneService;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[Group('prod-smoke')]
final class GitCloneRealSmokeTest extends KernelTestCase
{
    public function testShallowClonePublicRepoWhenEnabled(): void
    {
        if ('1' === ($_ENV['RUN_REAL_GIT_CLONE'] ?? getenv('RUN_REAL_GIT_CLONE'))) {
            // continue
        } else {
            self::markTestSkipped('Définir RUN_REAL_GIT_CLONE=1 pour valider P1-74.');
        }

        if (filter_var($_ENV['APP_WORKSPACE_STUB'] ?? getenv('APP_WORKSPACE_STUB') ?: '1', FILTER_VALIDATE_BOOL)) {
            self::markTestSkipped('APP_WORKSPACE_STUB=1 — clone réel désactivé.');
        }

        self::bootKernel();
        $git = self::getContainer()->get(GitCloneService::class);
        $target = sys_get_temp_dir().'/ticket-git-smoke-'.uniqid();
        $git->cloneShallow('https://github.com/symfony/symfony-docs.git', $target, 'main');
        $this->assertDirectoryExists($target.'/.git');
        $this->assertFileExists($target.'/README.md');
    }
}
