<?php

namespace App\Twig\Components;

use App\Service\AppSettingsService;
use App\Service\AppSettingsTester;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class SettingsLiveComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public bool $imapPollEnabled = false;

    #[LiveProp(writable: true)]
    public bool $imapUseFixture = true;

    #[LiveProp(writable: true)]
    public string $imapHost = '';

    #[LiveProp(writable: true)]
    public int $imapPort = 993;

    #[LiveProp(writable: true)]
    public string $imapUser = '';

    #[LiveProp(writable: true)]
    public string $imapPassword = '';

    #[LiveProp(writable: true)]
    public string $imapFolderInbox = 'INBOX';

    #[LiveProp(writable: true)]
    public bool $imapPasswordConfigured = false;

    #[LiveProp(writable: true)]
    public bool $webhookEnabled = true;

    #[LiveProp(writable: true)]
    public string $webhookSecret = '';

    #[LiveProp(writable: true)]
    public string $webhookAllowedIps = '';

    #[LiveProp(writable: true)]
    public bool $webhookSecretConfigured = false;

    #[LiveProp(writable: true)]
    public string $agentProfile = 'fixture_default';

    #[LiveProp(writable: true)]
    public string $agentBinary = '';

    #[LiveProp(writable: true)]
    public string $agentCodexBin = 'codex';

    #[LiveProp]
    public ?string $flashMessage = null;

    #[LiveProp]
    public ?string $flashType = null;

    #[LiveProp]
    public ?string $testResultImap = null;

    #[LiveProp]
    public ?string $testResultAgent = null;

    #[LiveProp]
    public ?string $testResultWebhook = null;

    #[LiveProp]
    public ?string $webhookCurlExample = null;

    public function __construct(
        private readonly AppSettingsService $settings,
        private readonly AppSettingsTester $tester,
        #[Autowire(param: 'agents')]
        private readonly array $agentsConfig,
    ) {
    }

    public function mount(): void
    {
        $this->loadFromService();
    }

    /** @return list<string> */
    public function getAgentProfiles(): array
    {
        return array_keys($this->agentsConfig);
    }

    public function getWebhookUrl(): string
    {
        return $this->settings->getWebhookIngestUrl();
    }

    public function getUpdatedAt(): ?string
    {
        return $this->settings->getLastUpdatedAt()?->format('d/m/Y H:i');
    }

    #[LiveAction]
    public function save(): void
    {
        $this->settings->save([
            'imap' => [
                'poll_enabled' => $this->imapPollEnabled,
                'use_fixture' => $this->imapUseFixture,
                'host' => $this->imapHost,
                'port' => $this->imapPort,
                'user' => $this->imapUser,
                'password' => $this->imapPassword,
                'folder_inbox' => $this->imapFolderInbox,
            ],
            'webhook' => [
                'enabled' => $this->webhookEnabled,
                'secret' => $this->webhookSecret,
                'allowed_ips' => $this->webhookAllowedIps,
            ],
            'agent' => [
                'profile' => $this->agentProfile,
                'binary' => $this->agentBinary,
                'codex_bin' => $this->agentCodexBin,
            ],
        ]);

        $this->loadFromService();
        $this->flash('Paramètres enregistrés.', 'success');
    }

    #[LiveAction]
    public function resetToEnvDefaults(): void
    {
        $this->settings->resetStored();
        $this->loadFromService();
        $this->flash('Retour aux valeurs .env (overrides UI effacés).', 'info');
    }

    #[LiveAction]
    public function testImap(): void
    {
        $result = $this->tester->testImap($this->imapFormData());
        $this->testResultImap = ($result['ok'] ? '✓ ' : '✗ ').$result['message'];
    }

    #[LiveAction]
    public function testAgent(): void
    {
        $result = $this->tester->testAgent($this->agentFormData());
        $this->testResultAgent = ($result['ok'] ? '✓ ' : '✗ ').$result['message'];
    }

    #[LiveAction]
    public function testWebhook(): void
    {
        $result = $this->tester->testWebhook($this->webhookFormData());
        $this->testResultWebhook = ($result['ok'] ? '✓ ' : '✗ ').$result['message'];
        $this->webhookCurlExample = $result['curl'];
    }

    private function loadFromService(): void
    {
        $data = $this->settings->getFormData();
        $imap = $data['imap'] ?? [];
        $webhook = $data['webhook'] ?? [];
        $agent = $data['agent'] ?? [];

        $this->imapPollEnabled = (bool) ($imap['poll_enabled'] ?? false);
        $this->imapUseFixture = (bool) ($imap['use_fixture'] ?? true);
        $this->imapHost = (string) ($imap['host'] ?? '');
        $this->imapPort = (int) ($imap['port'] ?? 993);
        $this->imapUser = (string) ($imap['user'] ?? '');
        $this->imapPassword = '';
        $this->imapPasswordConfigured = (bool) ($imap['password_configured'] ?? false);
        $this->imapFolderInbox = (string) ($imap['folder_inbox'] ?? 'INBOX');

        $this->webhookEnabled = (bool) ($webhook['enabled'] ?? true);
        $this->webhookSecret = '';
        $this->webhookSecretConfigured = (bool) ($webhook['secret_configured'] ?? false);
        $this->webhookAllowedIps = (string) ($webhook['allowed_ips'] ?? '');

        $this->agentProfile = (string) ($agent['profile'] ?? 'fixture_default');
        $this->agentBinary = (string) ($agent['binary'] ?? '');
        $this->agentCodexBin = (string) ($agent['codex_bin'] ?? 'codex');
    }

    /** @return array<string, mixed> */
    private function imapFormData(): array
    {
        return [
            'poll_enabled' => $this->imapPollEnabled,
            'use_fixture' => $this->imapUseFixture,
            'host' => $this->imapHost,
            'port' => $this->imapPort,
            'user' => $this->imapUser,
            'password' => $this->imapPassword,
            'folder_inbox' => $this->imapFolderInbox,
        ];
    }

    /** @return array<string, mixed> */
    private function agentFormData(): array
    {
        return [
            'profile' => $this->agentProfile,
            'binary' => $this->agentBinary,
            'codex_bin' => $this->agentCodexBin,
        ];
    }

    /** @return array<string, mixed> */
    private function webhookFormData(): array
    {
        return [
            'enabled' => $this->webhookEnabled,
            'secret' => $this->webhookSecret,
            'allowed_ips' => $this->webhookAllowedIps,
        ];
    }

    private function flash(string $message, string $type): void
    {
        $this->flashMessage = $message;
        $this->flashType = $type;
    }
}
