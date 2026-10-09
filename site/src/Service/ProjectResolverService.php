<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProjectResolverService
{
    private const AUTO_CONFIDENCE_THRESHOLD = 0.85;

    public function __construct(
        #[Autowire(param: 'projects')]
        private readonly array $projects,
    ) {
    }

    /**
     * @param array{project_key?: string, confidence?: float, domain?: string}|null $projectDetection
     *
     * @return array{status: string, project_key: ?string, candidates: list<string>}
     */
    public function resolve(?array $projectDetection, ?string $senderEmail = null, ?string $body = null): array
    {
        $candidates = [];

        $proposedKey = $projectDetection['project_key'] ?? null;
        $confidence = (float) ($projectDetection['confidence'] ?? 0);

        if (is_string($proposedKey) && isset($this->projects[$proposedKey]) && $confidence >= self::AUTO_CONFIDENCE_THRESHOLD) {
            return ['status' => 'auto', 'project_key' => $proposedKey, 'candidates' => [$proposedKey]];
        }

        $domainsInBody = $this->extractDomainsFromText($body ?? '');
        $senderDomain = $this->emailDomainFromAddress($senderEmail);

        foreach ($this->projects as $key => $config) {
            $projectDomains = $config['domains'] ?? [];
            foreach ($projectDomains as $domain) {
                if ($senderDomain === $domain || in_array($domain, $domainsInBody, true)) {
                    $candidates[$key] = true;
                }
            }
        }

        $candidateKeys = array_keys($candidates);

        if (1 === count($candidateKeys)) {
            return ['status' => 'auto', 'project_key' => $candidateKeys[0], 'candidates' => $candidateKeys];
        }

        if (is_string($proposedKey) && isset($this->projects[$proposedKey])) {
            $candidateKeys = array_values(array_unique([$proposedKey, ...$candidateKeys]));
        }

        if ([] === $candidateKeys) {
            return ['status' => 'failed', 'project_key' => null, 'candidates' => []];
        }

        if (1 === count($candidateKeys)) {
            return ['status' => 'auto', 'project_key' => $candidateKeys[0], 'candidates' => $candidateKeys];
        }

        return ['status' => 'ambiguous', 'project_key' => null, 'candidates' => $candidateKeys];
    }

    public function isValidProjectKey(string $key): bool
    {
        return isset($this->projects[$key]);
    }

    /** @return list<string> */
    public function getProjectKeys(): array
    {
        return array_keys($this->projects);
    }

    public function getProject(string $key): array
    {
        if (!isset($this->projects[$key])) {
            throw new \InvalidArgumentException('Projet inconnu : '.$key);
        }

        return $this->projects[$key];
    }

    /** @return list<string> */
    public function extractDomainsFromText(string $text): array
    {
        preg_match_all('/[a-z0-9][a-z0-9.-]+\.[a-z]{2,}/i', $text, $matches);

        return array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));
    }

    public function emailDomainFromAddress(?string $email): ?string
    {
        if (null === $email || !str_contains($email, '@')) {
            return null;
        }

        return strtolower(substr($email, (int) strrpos($email, '@') + 1));
    }
}
