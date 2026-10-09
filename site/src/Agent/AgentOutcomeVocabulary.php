<?php

namespace App\Agent;

/**
 * Vocabulaire contractuel des sorties agent (aligné CDC §18 + schémas Codex).
 */
final class AgentOutcomeVocabulary
{
    /** @var list<string> */
    public const TRIAGE_RESOLUTIONS = [
        'close_user_misuse',
        'close_admin_task',
        'close_content',
        'close_commercial',
        'close_ask_client',
        'close_informational',
        'needs_code',
        'needs_human',
        'needs_investigation',
    ];

    /** @var list<string> */
    public const TRIAGE_SERVICES = [
        'http_probe',
        'http_probe_batch',
        'clone_repository',
        'request_human_action',
        'close_ticket',
        'rerun_agent',
    ];

    /** @var list<string> */
    public const CODE_RESOLUTIONS = [
        'close_no_patch',
        'propose_patch',
        'needs_human',
        'needs_more_info',
    ];

    /** @var list<string> */
    public const CODE_NEXT_STEPS = [
        'close',
        'wait_human',
        'prepare_mr',
        'agent_loop',
    ];

    /** @var list<string> */
    public const HUMAN_ACTIONS = [
        'none',
        'contact_client',
        'verify_production',
        'check_admin_backoffice',
        'collect_server_logs',
        'validate_project_mapping',
        'test_form_submission',
    ];

    private const HUMAN_ACTION_LABELS = [
        'none' => 'Aucune action humaine spécifique',
        'contact_client' => 'Contacter le client',
        'verify_production' => 'Vérifier en production',
        'check_admin_backoffice' => 'Vérifier back-office / admin',
        'collect_server_logs' => 'Collecter les logs serveur',
        'validate_project_mapping' => 'Valider le projet / dépôt',
        'test_form_submission' => 'Tester l’envoi du formulaire',
    ];

    /**
     * @param array<string, mixed> $data
     */
    public static function assertTriageOutcome(array $data): void
    {
        self::assertFieldEnum($data, 'resolution', self::TRIAGE_RESOLUTIONS, 'triage');
        self::assertServiceRequestCoherent($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function assertCodeOutcome(array $data): void
    {
        self::assertFieldEnum($data, 'resolution', self::CODE_RESOLUTIONS, 'code_analysis');
        self::assertFieldEnum($data, 'next_step', self::CODE_NEXT_STEPS, 'code_analysis');
        if (array_key_exists('human_action', $data)) {
            self::assertFieldEnum($data, 'human_action', self::HUMAN_ACTIONS, 'code_analysis');
            self::assertHumanActionCoherent(
                (string) $data['next_step'],
                (string) $data['human_action'],
                'code_analysis',
            );
        }
    }

    public static function humanActionLabel(string $action): string
    {
        return self::HUMAN_ACTION_LABELS[$action] ?? $action;
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function assertServiceRequestCoherent(array $data): void
    {
        $request = $data['service_request'] ?? null;
        if (!is_array($request)) {
            throw new \InvalidArgumentException('service_request manquant ou invalide (triage v2).');
        }

        $service = (string) ($request['service'] ?? '');
        if (!in_array($service, self::TRIAGE_SERVICES, true)) {
            throw new \InvalidArgumentException('service_request.service invalide : '.$service);
        }

        $params = $request['params'] ?? null;
        if (!is_array($params)) {
            throw new \InvalidArgumentException('service_request.params doit être un objet.');
        }

        match ($service) {
            'http_probe' => self::assertHttpProbeParams($params),
            'http_probe_batch' => self::assertHttpProbeBatchParams($params),
            'request_human_action' => self::assertHumanActionParams($params),
            default => null,
        };
    }

    /** @param array<string, mixed> $params */
    private static function assertHttpProbeParams(array $params): void
    {
        $url = trim((string) ($params['url'] ?? ''));
        if ('' === $url) {
            throw new \InvalidArgumentException('http_probe exige params.url non vide.');
        }
    }

    /** @param array<string, mixed> $params */
    private static function assertHttpProbeBatchParams(array $params): void
    {
        $urls = $params['urls'] ?? null;
        if (!is_array($urls) || [] === $urls) {
            throw new \InvalidArgumentException('http_probe_batch exige params.urls non vide.');
        }
    }

    /** @param array<string, mixed> $params */
    private static function assertHumanActionParams(array $params): void
    {
        $action = (string) ($params['human_action'] ?? '');
        if ('' === $action || 'none' === $action || !in_array($action, self::HUMAN_ACTIONS, true)) {
            throw new \InvalidArgumentException('request_human_action exige params.human_action valide.');
        }
    }

    /**
     * @param list<string> $allowed
     */
    private static function assertFieldEnum(array $data, string $field, array $allowed, string $phase): void
    {
        if (!isset($data[$field]) || !\in_array($data[$field], $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Champ %s invalide pour %s : %s (attendu : %s)',
                $field,
                $phase,
                isset($data[$field]) ? (string) $data[$field] : 'null',
                implode(', ', $allowed),
            ));
        }
    }

    private static function assertHumanActionCoherent(string $nextStep, string $humanAction, string $phase): void
    {
        $needsHumanAction = \in_array($nextStep, ['wait_human', 'prepare_mr'], true);
        $automationSteps = \in_array($nextStep, ['agent_loop', 'close'], true);

        if ($needsHumanAction && ('none' === $humanAction || '' === $humanAction)) {
            throw new \InvalidArgumentException(sprintf(
                'human_action ne peut pas être "none" lorsque next_step=%s (%s)',
                $nextStep,
                $phase,
            ));
        }

        if ($automationSteps && 'none' !== $humanAction) {
            throw new \InvalidArgumentException(sprintf(
                'human_action doit être "none" lorsque next_step=%s (%s), reçu %s',
                $nextStep,
                $phase,
                $humanAction,
            ));
        }
    }
}
