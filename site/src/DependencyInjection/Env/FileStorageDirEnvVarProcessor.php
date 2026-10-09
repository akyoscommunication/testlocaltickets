<?php

namespace App\DependencyInjection\Env;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Extrait le répertoire local depuis FILE_STORAGE_DSN (ex. local:///path/to/data).
 */
#[AutoconfigureTag('container.env_var_processor')]
final class FileStorageDirEnvVarProcessor implements EnvVarProcessorInterface
{
    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $dsn = (string) $getEnv($name);
        if (str_starts_with($dsn, 'local://')) {
            $path = rtrim(substr($dsn, 8), '/');
            $projectDir = \dirname(__DIR__, 3);

            return str_replace('%kernel.project_dir%', $projectDir, $path);
        }

        throw new \InvalidArgumentException(sprintf(
            'FILE_STORAGE_DSN non supporté : %s (attendu local:///chemin)',
            $dsn,
        ));
    }

    public static function getProvidedTypes(): array
    {
        return ['file_storage_dir' => 'string'];
    }
}
