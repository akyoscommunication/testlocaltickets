<?php

namespace App\Agent;

/**
 * Runner de développement : simule l'IA sans CLI externe.
 */
final class FixtureAgentRunner implements AgentRunnerInterface
{
    public function getName(): string
    {
        return 'fixture';
    }

    public function run(AgentRunRequest $request): AgentRunResult
    {
        $payload = json_decode((string) file_get_contents($request->requestFilePath), true, 512, JSON_THROW_ON_ERROR);
        $body = strtolower((string) ($payload['body'] ?? ''));
        $subject = strtolower((string) ($payload['subject'] ?? ''));

        if (str_contains($body, '__agent_fail__')) {
            return new AgentRunResult(
                false,
                stderr: 'Échec simulé (fixture __agent_fail__)',
                exitCode: 1,
                errorSummary: 'Échec simulé (fixture __agent_fail__)',
            );
        }

        $result = match ($request->phase) {
            'triage' => $this->triageResult($request->ticketNumber, $body, $subject, $payload),
            'code_analysis' => $this->codeResult($request),
            default => throw new \RuntimeException('Phase non supportée : '.$request->phase),
        };

        $dir = dirname($request->resultFilePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents($request->resultFilePath, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if (is_dir($request->agentRunDir)) {
            file_put_contents($request->agentRunDir.'/response.json', json_encode($result, JSON_THROW_ON_ERROR));
        }

        return new AgentRunResult(true);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function triageResult(string $ticketNumber, string $body, string $subject, array $payload): array
    {
        if (str_contains($body, '__http_probe__')) {
            $detection = $this->defaultProjectDetection($payload);

            return $this->triageV2($ticketNumber, [
                'resolution' => 'needs_investigation',
                'service' => 'http_probe',
                'params' => [
                    'url' => 'https://'.$detection['domain'].'/',
                    'purpose' => 'homepage_links',
                ],
                'summary' => 'Fixture : sonde HTTP demandée.',
                'internal_note' => 'Fixture http_probe.',
                'project_detection' => $detection + [
                    'confidence' => 0.9,
                    'reason' => 'Domaine dans le ticket (fixture)',
                ],
            ]);
        }

        $needsCode = str_contains($body, 'bug') || str_contains($body, 'erreur') || str_contains($subject, 'bug');

        if ($needsCode) {
            return $this->triageV2($ticketNumber, [
                'resolution' => 'needs_code',
                'service' => 'clone_repository',
                'params' => [],
                'summary' => 'Analyse technique requise sur le dépôt.',
                'internal_note' => 'Lancer analyse code sur le repo détecté.',
                'project_detection' => [
                    'project_key' => $payload['projects_catalog'][0]['key'] ?? 'client-site',
                    'domain' => $payload['projects_catalog'][0]['domains'][0] ?? 'client.fr',
                    'confidence' => 0.75,
                    'reason' => 'Heuristique fixture (mots-clés bug/erreur)',
                ],
            ]);
        }

        return $this->triageV2($ticketNumber, [
            'resolution' => 'close_admin_task',
            'service' => 'close_ticket',
            'params' => [],
            'summary' => 'Intervention administrateur sans modification de code.',
            'internal_note' => 'Vérifier le back-office / configuration.',
            'project_detection' => $this->defaultProjectDetection($payload) + [
                'confidence' => 0.6,
                'reason' => 'Fixture par défaut',
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array{project_key: string, domain: string}
     */
    private function defaultProjectDetection(array $payload): array
    {
        $domain = (string) ($payload['inferred_site_domain'] ?? '');
        if ('' === $domain && isset($payload['extracted_domains'][0])) {
            $domain = (string) $payload['extracted_domains'][0];
        }

        $catalog = is_array($payload['projects_catalog'] ?? null) ? $payload['projects_catalog'] : [];
        foreach ($catalog as $project) {
            if (!is_array($project)) {
                continue;
            }
            $key = (string) ($project['key'] ?? '');
            $domains = is_array($project['domains'] ?? null) ? $project['domains'] : [];
            if ('' !== $domain && \in_array($domain, $domains, true)) {
                return ['project_key' => $key, 'domain' => $domain];
            }
            if ('' === $domain && isset($domains[0])) {
                return ['project_key' => $key, 'domain' => (string) $domains[0]];
            }
        }

        if ('' !== $domain) {
            return ['project_key' => 'unknown', 'domain' => $domain];
        }

        return ['project_key' => 'unknown', 'domain' => 'unknown'];
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function triageV2(string $ticketNumber, array $overrides): array
    {
        $service = (string) ($overrides['service'] ?? 'close_ticket');
        $params = is_array($overrides['params'] ?? null) ? $overrides['params'] : [];
        unset($overrides['service'], $overrides['params']);

        return array_merge([
            'schema_version' => '2',
            'ticket_number' => $ticketNumber,
            'phase' => 'triage',
            'confidence' => 0.91,
            'client_reply_draft' => 'Bonjour, nous traitons votre demande.',
            'service_request' => [
                'service' => $service,
                'params' => $params,
            ],
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function codeResult(AgentRunRequest $request): array
    {
        $payload = json_decode((string) file_get_contents($request->requestFilePath), true, 512, JSON_THROW_ON_ERROR);
        $body = strtolower((string) ($payload['body'] ?? ''));
        $ticketNumber = $request->ticketNumber;

        if (str_contains($body, '__agent_loop__')) {
            $priorResults = glob(dirname($request->resultFilePath).'/*.json') ?: [];
            if (\count($priorResults) >= 1) {
                return [
                    'schema_version' => '1',
                    'ticket_number' => $ticketNumber,
                    'phase' => 'code_analysis',
                    'resolution' => 'close_no_patch',
                    'next_step' => 'close',
                    'human_action' => 'none',
                    'confidence' => 0.85,
                    'summary' => 'Boucle agent terminée (fixture, 2e passage).',
                    'client_reply_draft' => 'Bonjour, nous avons finalisé l’analyse.',
                    'internal_note' => 'agent_loop consommé.',
                ];
            }

            return [
                'schema_version' => '1',
                'ticket_number' => $ticketNumber,
                'phase' => 'code_analysis',
                'resolution' => 'needs_more_info',
                'next_step' => 'agent_loop',
                'human_action' => 'none',
                'confidence' => 0.7,
                'summary' => 'Informations complémentaires requises (fixture agent_loop).',
                'client_reply_draft' => 'Nous poursuivons l’analyse.',
                'internal_note' => 'Relance automatique agent_loop.',
            ];
        }

        return [
            'schema_version' => '1',
            'ticket_number' => $ticketNumber,
            'phase' => 'code_analysis',
            'resolution' => 'close_no_patch',
            'next_step' => 'close',
            'human_action' => 'none',
            'confidence' => 0.82,
            'summary' => 'Analyse code terminée — aucun patch nécessaire pour ce ticket (fixture).',
            'client_reply_draft' => 'Bonjour, le correctif est en cours de validation.',
            'internal_note' => 'Revue humaine recommandée avant réponse client.',
        ];
    }
}
