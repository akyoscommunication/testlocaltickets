<?php

namespace App\Service;

use App\Agent\CodexCliRunner;
use App\Input\ImapInputSource;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AppSettingsTester
{
    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly ImapInputSource $imap,
        private readonly CodexCliRunner $codexRunner,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'agents')]
        private readonly array $agentsConfig,
    ) {
    }

    /**
     * @param array<string, mixed> $formImap
     *
     * @return array{ok: bool, message: string}
     */
    public function testImap(array $formImap): array
    {
        $config = $this->mergeImapConfig($formImap);
        $result = $this->imap->testConnectionWithConfig($config);

        return [
            'ok' => $result['ok'],
            'message' => $result['message'],
        ];
    }

    /**
     * @param array<string, mixed> $formAgent
     *
     * @return array{ok: bool, message: string}
     */
    public function testAgent(array $formAgent): array
    {
        $profile = (string) ($formAgent['profile'] ?? 'fixture_default');
        $profileConfig = $this->agentsConfig[$profile] ?? null;
        $runner = $profileConfig['runner'] ?? 'fixture';

        if ('fixture' === $runner) {
            return [
                'ok' => true,
                'message' => sprintf('Profil « %s » (fixture) — pas de CLI externe requis.', $profile),
            ];
        }

        $binary = (string) ($formAgent['binary'] ?? '');
        if ('' === $binary) {
            $binary = $this->settings->getAgentBinary();
        }
        if ('' === $binary || !is_executable($binary)) {
            $binary = $this->codexRunner->resolveBinary();
        }

        if (!is_executable($binary)) {
            return [
                'ok' => false,
                'message' => sprintf('Binaire introuvable ou non exécutable : %s', $binary),
            ];
        }

        $process = new Process([$binary, '--help']);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            return [
                'ok' => false,
                'message' => trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Échec --help',
            ];
        }

        $codexBin = (string) ($formAgent['codex_bin'] ?? '');
        if ('' === $codexBin) {
            $codexBin = $this->settings->getCodexBin();
        }

        $codexProcess = new Process([$codexBin, '--version']);
        $codexProcess->setTimeout(15);
        $codexProcess->run();

        $codexLine = trim($codexProcess->getOutput() ?: $codexProcess->getErrorOutput());
        if (!$codexProcess->isSuccessful()) {
            return [
                'ok' => false,
                'message' => sprintf('Wrapper OK (%s) mais « %s --version » a échoué : %s', basename($binary), $codexBin, $codexLine ?: 'erreur'),
            ];
        }

        return [
            'ok' => true,
            'message' => sprintf('Wrapper %s OK — %s', basename($binary), $codexLine),
        ];
    }

    /**
     * @param array<string, mixed> $formWebhook
     *
     * @return array{ok: bool, message: string, curl: string}
     */
    public function testWebhook(array $formWebhook): array
    {
        $enabled = (bool) ($formWebhook['enabled'] ?? false);
        $secret = (string) ($formWebhook['secret'] ?? '');
        if ('' === $secret) {
            $secret = $this->settings->getWebhookSecret();
        }

        $url = $this->settings->getWebhookIngestUrl();
        $payload = json_encode([
            'ticket_number' => 'TEST-WEBHOOK-'.time(),
            'subject' => 'Test webhook paramètres',
            'body' => 'Ping depuis la page Paramètres.',
        ], JSON_THROW_ON_ERROR);

        $curl = sprintf(
            "curl -sS -X POST '%s' \\\n  -H 'Authorization: Bearer %s' \\\n  -H 'Content-Type: application/json' \\\n  -d '%s'",
            $url,
            $secret,
            addslashes($payload),
        );

        if (!$enabled) {
            return [
                'ok' => false,
                'message' => 'Webhook désactivé — activez-le pour accepter les requêtes.',
                'curl' => $curl,
            ];
        }

        if ('' === $secret) {
            return [
                'ok' => false,
                'message' => 'Secret webhook vide.',
                'curl' => $curl,
            ];
        }

        $request = $this->requestStack->getCurrentRequest();
        if (null === $request) {
            return [
                'ok' => true,
                'message' => 'Commande curl générée (pas de requête HTTP interne sans contexte).',
                'curl' => $curl,
            ];
        }

        try {
            $response = $this->httpClient->request('POST', $url, [
                'headers' => [
                    'Authorization' => 'Bearer '.$secret,
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload,
                'timeout' => 10,
            ]);

            $status = $response->getStatusCode();
            $body = $response->getContent(false);

            return [
                'ok' => $status >= 200 && $status < 300,
                'message' => sprintf('HTTP %d — %s', $status, $body),
                'curl' => $curl,
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => 'Requête interne échouée : '.$e->getMessage(),
                'curl' => $curl,
            ];
        }
    }

    /** @return list<string> */
    public function getAgentProfileChoices(): array
    {
        return array_keys($this->agentsConfig);
    }

    /**
     * @param array<string, mixed> $formImap
     *
     * @return array<string, mixed>
     */
    private function mergeImapConfig(array $formImap): array
    {
        $current = $this->settings->getImapConfig();
        $merged = array_replace_recursive($current, $formImap);
        if ('' === ($formImap['password'] ?? '')) {
            $merged['password'] = $current['password'] ?? '';
        }

        return $merged;
    }
}
