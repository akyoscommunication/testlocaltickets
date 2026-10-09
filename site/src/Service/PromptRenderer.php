<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Environment;

final class PromptRenderer
{
    public function __construct(
        private readonly Environment $twig,
        #[Autowire(param: 'prompts')]
        private readonly array $promptsConfig,
    ) {
    }

    /**
     * @return array{system: string, user: string, template_key: string, template_version: int}
     */
    public function render(string $phase, array $variables): array
    {
        if (!isset($this->promptsConfig[$phase])) {
            throw new \InvalidArgumentException(sprintf('Phase prompt inconnue : %s', $phase));
        }

        $config = $this->promptsConfig[$phase];
        $systemPath = $config['system'];
        $userPath = $config['user'];

        $system = is_file($systemPath) ? (string) file_get_contents($systemPath) : '';
        if (\in_array($phase, ['triage', 'code_analysis'], true)) {
            $stylePath = $this->promptsConfig['client_reply_style'] ?? null;
            if (\is_string($stylePath) && is_file($stylePath)) {
                $system .= "\n\n".(string) file_get_contents($stylePath);
            }
        }
        $user = match (true) {
            str_ends_with($userPath, '.twig') => $this->twig->createTemplate(
                (string) file_get_contents($userPath)
            )->render($variables),
            is_file($userPath) => (string) file_get_contents($userPath),
            default => '',
        };

        return [
            'system' => $system,
            'user' => $user,
            'template_key' => $phase,
            'template_version' => (int) ($config['version'] ?? 1),
        ];
    }
}
