<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Enrichissement déterministe du contexte ticket (domaines, expéditeur) pour le triage.
 */
final class TicketContextEnricher
{
    public function __construct(
        private readonly ProjectResolverService $projectResolver,
        #[Autowire(param: 'projects')]
        private readonly array $projects,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{
     *   sender_email: ?string,
     *   signature_text: ?string,
     *   extracted_domains: list<string>,
     *   sender_domain: ?string,
     *   inferred_site_domain: ?string
     * }
     */
    public function enrichFromPayload(array $payload): array
    {
        $body = (string) ($payload['body'] ?? '');
        $raw = is_array($payload['raw'] ?? null) ? $payload['raw'] : [];
        $signature = (string) ($payload['signature'] ?? $raw['signature'] ?? '');
        $from = (string) ($payload['from'] ?? $raw['from'] ?? $raw['sender'] ?? '');

        $combined = trim($body."\n".$signature);
        $extracted = $this->projectResolver->extractDomainsFromText($combined);
        $senderDomain = $this->projectResolver->emailDomainFromAddress('' !== $from ? $from : null);

        if (null !== $senderDomain && !in_array($senderDomain, $extracted, true)) {
            $extracted[] = $senderDomain;
        }

        return [
            'sender_email' => '' !== $from ? $from : null,
            'signature_text' => '' !== $signature ? $signature : null,
            'extracted_domains' => $extracted,
            'sender_domain' => $senderDomain,
            'inferred_site_domain' => $this->inferPrimarySiteDomain($extracted, $senderDomain),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string> hôtes autorisés pour les sondes HTTP (sans schéma)
     */
    public function buildProbeAllowlist(array $payload): array
    {
        $enriched = $this->enrichFromPayload($payload);
        $hosts = $enriched['extracted_domains'];

        if (null !== $enriched['inferred_site_domain']) {
            $hosts[] = $enriched['inferred_site_domain'];
        }

        foreach ($this->projects as $config) {
            foreach ($config['domains'] ?? [] as $domain) {
                $hosts[] = strtolower($domain);
            }
        }

        $normalized = [];
        foreach ($hosts as $host) {
            $host = strtolower(trim($host));
            if ('' === $host) {
                continue;
            }
            $normalized[$host] = true;
            if (str_starts_with($host, 'www.')) {
                $normalized[substr($host, 4)] = true;
            } else {
                $normalized['www.'.$host] = true;
            }
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $domains
     */
    private function inferPrimarySiteDomain(array $domains, ?string $senderDomain): ?string
    {
        $freeMail = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'live.com', 'icloud.com', 'orange.fr', 'free.fr'];
        foreach ($domains as $domain) {
            if (!in_array($domain, $freeMail, true)) {
                return $domain;
            }
        }

        return $senderDomain && !in_array($senderDomain, $freeMail, true) ? $senderDomain : ($domains[0] ?? null);
    }
}
