<?php

namespace App\Service;

use App\Entity\AppConfiguration;
use App\Repository\AppConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;

final class AppSettingsService
{
    /** @var array<string, mixed>|null */
    private ?array $resolved = null;

    public function __construct(
        private readonly AppConfigurationRepository $configurations,
        private readonly EntityManagerInterface $em,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'app.settings.defaults')]
        private readonly array $defaults,
    ) {
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        if (null !== $this->resolved) {
            return $this->resolved;
        }

        $stored = $this->configurations->getSingleton()?->getSettings() ?? [];

        return $this->resolved = array_replace_recursive($this->defaults, $stored);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $value = $this->all();
        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function getAgentProfile(): string
    {
        return (string) $this->get('agent.profile', 'fixture_default');
    }

    public function getAgentBinary(): string
    {
        return (string) $this->get('agent.binary', '');
    }

    public function getCodexBin(): string
    {
        $bin = (string) $this->get('agent.codex_bin', '');

        return '' !== $bin ? $bin : 'codex';
    }

    public function isWebhookEnabled(): bool
    {
        return (bool) $this->get('webhook.enabled', true);
    }

    public function getWebhookSecret(): string
    {
        return (string) $this->get('webhook.secret', '');
    }

    public function isImapPollEnabled(): bool
    {
        return (bool) $this->get('imap.poll_enabled', false);
    }

    /** @return array<string, mixed> */
    public function getImapConfig(): array
    {
        return (array) $this->get('imap', []);
    }

    /**
     * @param array<string, mixed> $imap
     */
    public function hasConfiguredRealImapHost(array $imap): bool
    {
        $host = (string) ($imap['host'] ?? '');

        return '' !== $host && 'imap.example.com' !== $host;
    }

    /** @return array<string, mixed> form values with secrets blanked for display */
    public function getFormData(): array
    {
        $data = $this->all();
        if ('' !== ($data['imap']['password'] ?? '')) {
            $data['imap']['password'] = '';
            $data['imap']['password_configured'] = true;
        }
        if ('' !== ($data['webhook']['secret'] ?? '')) {
            $data['webhook']['secret'] = '';
            $data['webhook']['secret_configured'] = true;
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $submitted
     */
    public function save(array $submitted): void
    {
        $current = $this->all();
        $merged = array_replace_recursive($current, $submitted);

        if ('' === ($submitted['imap']['password'] ?? '')) {
            $merged['imap']['password'] = $current['imap']['password'] ?? '';
        }
        if ('' === ($submitted['webhook']['secret'] ?? '')) {
            $merged['webhook']['secret'] = $current['webhook']['secret'] ?? '';
        }

        unset($merged['imap']['password_configured'], $merged['webhook']['secret_configured']);

        $overrides = $this->onlyStoredOverrides($merged);
        if (isset($submitted['imap'])) {
            foreach (['poll_enabled', 'use_fixture'] as $imapToggle) {
                if (array_key_exists($imapToggle, $merged['imap'])) {
                    $overrides['imap'][$imapToggle] = $merged['imap'][$imapToggle];
                }
            }
            if ($this->hasConfiguredRealImapHost($merged['imap']) && !($merged['imap']['use_fixture'] ?? false)) {
                $overrides['imap']['use_fixture'] = false;
            }
        }

        $entity = $this->configurations->getSingleton() ?? new AppConfiguration();
        $entity->setSettings($overrides);
        $this->em->persist($entity);
        $this->em->flush();
        $this->resolved = null;
    }

    public function getWebhookIngestUrl(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return '/api/v1/tickets/ingest';
        }

        return $request->getSchemeAndHttpHost().'/api/v1/tickets/ingest';
    }

    public function resetStored(): void
    {
        $entity = $this->configurations->getSingleton();
        if (null !== $entity) {
            $this->em->remove($entity);
            $this->em->flush();
        }
        $this->resolved = null;
    }

    public function getLastUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->configurations->getSingleton()?->getUpdatedAt();
    }

    /** @param array<string, mixed> $merged */
    private function onlyStoredOverrides(array $merged): array
    {
        $overrides = [];
        foreach ($merged as $section => $values) {
            if (!is_array($values)) {
                continue;
            }
            $defaultSection = $this->defaults[$section] ?? [];
            foreach ($values as $key => $value) {
                if (($defaultSection[$key] ?? null) !== $value) {
                    $overrides[$section][$key] = $value;
                }
            }
        }

        return $overrides;
    }
}
