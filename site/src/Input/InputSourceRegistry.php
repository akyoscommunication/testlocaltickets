<?php

namespace App\Input;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class InputSourceRegistry
{
    /** @var array<string, InputSourceInterface> */
    private array $byKey;

    /**
     * @param iterable<InputSourceInterface> $sources
     * @param array<string, array{enabled?: bool}> $config
     */
    public function __construct(
        #[AutowireIterator('app.input_source')]
        iterable $sources,
        #[Autowire('%input_sources%')]
        private readonly array $config,
    ) {
        $this->byKey = [];
        foreach ($sources as $source) {
            $this->byKey[$source->getKey()] = $source;
        }
    }

    public function get(string $key): InputSourceInterface
    {
        if (!isset($this->byKey[$key])) {
            throw new \InvalidArgumentException(sprintf('Source d’entrée inconnue : %s', $key));
        }

        return $this->byKey[$key];
    }

    /**
     * @return list<InputSourceInterface>
     */
    public function all(): array
    {
        return array_values($this->byKey);
    }

    public function has(string $key): bool
    {
        return isset($this->byKey[$key]);
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->config[$key]['enabled'] ?? false);
    }

    /**
     * @return list<InputSourceInterface>
     */
    public function enabled(): array
    {
        return array_values(array_filter(
            $this->all(),
            fn (InputSourceInterface $source): bool => $this->isEnabled($source->getKey()),
        ));
    }
}
