<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sondes HTTP GET contrôlées (allowlist, pas de SSRF vers réseaux privés).
 */
final class HttpProbeService
{
    private const MAX_BODY_BYTES = 51200;
    private const USER_AGENT = 'TicketAgent-Probe/1.0';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param list<string> $allowedHosts noms d'hôte autorisés (ex. caladair.com)
     *
     * @return array{
     *   method: string,
     *   url: string,
     *   purpose: string,
     *   status_code: ?int,
     *   final_url: ?string,
     *   content_type: ?string,
     *   body_excerpt: ?string,
     *   contact_links: list<string>,
     *   error: ?string,
     *   probed_at: string
     * }
     */
    public function probeGet(string $url, string $purpose, array $allowedHosts): array
    {
        $base = [
            'method' => 'GET',
            'url' => $url,
            'purpose' => $purpose,
            'status_code' => null,
            'final_url' => null,
            'content_type' => null,
            'body_excerpt' => null,
            'contact_links' => [],
            'error' => null,
            'probed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        try {
            $host = $this->parseAndValidateHost($url, $allowedHosts);
        } catch (\InvalidArgumentException $e) {
            $base['error'] = $e->getMessage();

            return $base;
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => self::USER_AGENT],
                'max_redirects' => 5,
                'timeout' => 15,
            ]);
            $status = $response->getStatusCode();
            $headers = $response->getHeaders(false);
            $contentType = $headers['content-type'][0] ?? null;
            $body = $response->getContent(false);
            if (strlen($body) > self::MAX_BODY_BYTES) {
                $body = substr($body, 0, self::MAX_BODY_BYTES);
            }

            $base['status_code'] = $status;
            $base['final_url'] = $response->getInfo('url') ?? $url;
            $base['content_type'] = is_string($contentType) ? $contentType : null;
            $base['body_excerpt'] = $body;
            $base['contact_links'] = $this->extractContactLinks($body, $host);
        } catch (\Throwable $e) {
            $base['error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @param list<string>      $urls
     * @param list<string>      $allowedHosts
     *
     * @return array{
     *   requested_count: int,
     *   purpose: string,
     *   results: list<array<string, mixed>>,
     *   summary_lines: list<string>,
     *   probed_at: string
     * }
     */
    public function probeGetBatch(array $urls, string $purpose, array $allowedHosts): array
    {
        $results = [];
        $summaryLines = [];

        foreach ($urls as $url) {
            $result = $this->probeGet($url, $purpose, $allowedHosts);
            $results[] = $result;
            $status = $result['status_code'] ?? '?';
            $finalUrl = $result['final_url'] ?? $result['url'] ?? $url;
            $line = sprintf('GET %s → HTTP %s', $finalUrl, $status);
            if (is_string($result['error'] ?? null) && '' !== $result['error']) {
                $line .= ' ('.$result['error'].')';
            }
            $summaryLines[] = $line;
        }

        return [
            'requested_count' => count($urls),
            'purpose' => $purpose,
            'results' => $results,
            'summary_lines' => $summaryLines,
            'probed_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    /**
     * @param list<string> $allowedHosts
     */
    private function parseAndValidateHost(string $url, array $allowedHosts): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw new \InvalidArgumentException('URL invalide : '.$url);
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException('Schéma non autorisé (http/https uniquement).');
        }

        $host = strtolower($parts['host']);
        if ($this->isBlockedHost($host)) {
            throw new \InvalidArgumentException('Hôte interdit : '.$host);
        }

        $allowed = false;
        foreach ($allowedHosts as $allowedHost) {
            $allowedHost = strtolower($allowedHost);
            if ($host === $allowedHost || str_ends_with($host, '.'.$allowedHost)) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            throw new \InvalidArgumentException(sprintf(
                'Hôte %s hors allowlist ticket (%s).',
                $host,
                implode(', ', $allowedHosts),
            ));
        }

        return $host;
    }

    private function isBlockedHost(string $host): bool
    {
        if (in_array($host, ['localhost', '127.0.0.1', '::1', '0.0.0.0'], true)) {
            return true;
        }

        $ip = gethostbyname($host);
        if ($ip === $host && !filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }

        return !filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        );
    }

    /**
     * @return list<string>
     */
    private function extractContactLinks(string $html, string $host): array
    {
        $links = [];
        if (preg_match_all('/href\s*=\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $href) {
                $href = trim($href);
                if ('' === $href || str_starts_with($href, '#') || str_starts_with($href, 'mailto:')) {
                    continue;
                }
                if (!preg_match('/contact|nous-contacter|nous_contacter|get-in-touch/i', $href)) {
                    continue;
                }
                if (str_starts_with($href, 'http')) {
                    $links[] = $href;
                } elseif (str_starts_with($href, '/')) {
                    $links[] = 'https://'.$host.$href;
                }
            }
        }

        return array_values(array_unique($links));
    }
}
