<?php

namespace App\Tests\Unit;

use App\DependencyInjection\Env\FileStorageDirEnvVarProcessor;
use PHPUnit\Framework\TestCase;

final class FileStorageDirEnvVarProcessorTest extends TestCase
{
    public function testParsesLocalDsn(): void
    {
        $processor = new FileStorageDirEnvVarProcessor();
        $dir = $processor->getEnv('file_storage_dir', 'FILE_STORAGE_DSN', static fn () => 'local:///var/tickets');

        $this->assertSame('/var/tickets', $dir);
    }

    public function testResolvesKernelProjectDirPlaceholder(): void
    {
        $processor = new FileStorageDirEnvVarProcessor();
        $projectDir = \dirname(__DIR__, 2);
        $dir = $processor->getEnv('file_storage_dir', 'FILE_STORAGE_DSN', static fn () => 'local://%kernel.project_dir%/data');

        $this->assertSame($projectDir.'/data', $dir);
    }

    public function testRejectsUnknownScheme(): void
    {
        $processor = new FileStorageDirEnvVarProcessor();
        $this->expectException(\InvalidArgumentException::class);
        $processor->getEnv('file_storage_dir', 'FILE_STORAGE_DSN', static fn () => 's3://bucket');
    }
}
