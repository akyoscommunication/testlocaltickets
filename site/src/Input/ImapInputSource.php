<?php

namespace App\Input;

use App\Dto\IngestTicketDto;
use App\Repository\TicketProcessingRepository;
use App\Service\AppSettingsService;
use App\Service\ImapProcessedMessageStore;
use App\Service\TicketNumberGenerator;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AutoconfigureTag('app.input_source')]
final class ImapInputSource implements InputSourceInterface
{
    private const DEFAULT_POLL_LOOKBACK = 20;

    public function __construct(
        private readonly TicketNumberGenerator $ticketNumbers,
        private readonly AppSettingsService $settings,
        private readonly ImapProcessedMessageStore $processedMessages,
        private readonly TicketProcessingRepository $tickets,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getKey(): string
    {
        return 'imap';
    }

    /**
     * Test connexion / aperçu sans ingérer (fixtures non déplacées, registre local inchangé).
     *
     * @return array{ok: bool, message: string, preview_count: int}
     */
    public function testConnection(): array
    {
        return $this->testConnectionWithConfig($this->settings->getImapConfig());
    }

    /**
     * @param array<string, mixed> $imap
     *
     * @return array{ok: bool, message: string, preview_count: int}
     */
    public function testConnectionWithConfig(array $imap): array
    {
        try {
            $pending = $this->collectPending($imap, moveFixtureFiles: false, updateProcessedRegistry: false);

            $mode = !(bool) ($imap['use_fixture'] ?? true) && $this->settings->hasConfiguredRealImapHost($imap)
                ? 'IMAP réel'
                : 'fixture var/imap-inbox';

            return [
                'ok' => true,
                'message' => sprintf('[%s] %d message(s) en attente pour cet outil.', $mode, count($pending)),
                'preview_count' => count($pending),
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'message' => $e->getMessage(),
                'preview_count' => 0,
            ];
        }
    }

    public function poll(): array
    {
        return $this->collectPending(
            $this->settings->getImapConfig(),
            moveFixtureFiles: true,
            updateProcessedRegistry: true,
        );
    }

    /**
     * @param array<string, mixed> $imap
     *
     * @return list<IngestTicketDto>
     */
    private function collectPending(array $imap, bool $moveFixtureFiles, bool $updateProcessedRegistry): array
    {
        if ((bool) ($imap['use_fixture'] ?? true)) {
            return $this->collectPendingFixtureInbox($imap, $moveFixtureFiles, $updateProcessedRegistry);
        }

        if ($this->settings->hasConfiguredRealImapHost($imap)) {
            if (!class_exists(\Webklex\PHPIMAP\ClientManager::class)) {
                throw new \RuntimeException('webklex/php-imap requis pour le poll IMAP réel (composer require webklex/php-imap).');
            }

            return $this->collectPendingRealInbox($imap, $updateProcessedRegistry);
        }

        return $this->collectPendingFixtureInbox($imap, $moveFixtureFiles, $updateProcessedRegistry);
    }

    /**
     * @param array<string, mixed> $imap
     *
     * @return list<IngestTicketDto>
     */
    private function collectPendingFixtureInbox(array $imap, bool $moveFixtureFiles, bool $updateProcessedRegistry): array
    {
        $inboxDir = $this->projectDir.'/var/imap-inbox';
        if (!is_dir($inboxDir)) {
            return [];
        }

        $processedDir = $this->projectDir.'/var/imap-processed';
        if ($moveFixtureFiles && !is_dir($processedDir)) {
            mkdir($processedDir, 0775, true);
        }

        $dtos = [];
        $reservedTicketNumbers = [];
        foreach (glob($inboxDir.'/*.json') ?: [] as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            if (!is_array($data)) {
                continue;
            }

            $messageId = (string) ($data['message_id'] ?? basename($file));
            if (!$this->shouldIngestMessage($messageId, $updateProcessedRegistry)) {
                continue;
            }

            $ticketNumber = isset($data['ticket_number']) && is_string($data['ticket_number']) && '' !== $data['ticket_number']
                ? $data['ticket_number']
                : $this->ticketNumbers->generate($reservedTicketNumbers);
            $reservedTicketNumbers[] = $ticketNumber;

            $dtos[] = new IngestTicketDto(
                ticketNumber: $ticketNumber,
                inputSource: 'imap',
                subject: (string) ($data['subject'] ?? ''),
                body: (string) ($data['body'] ?? ''),
                sourceRef: $messageId,
                rawPayload: [
                    'from' => $data['from'] ?? null,
                    'message_id' => $messageId,
                    'fixture_file' => basename($file),
                ],
                attachments: $data['attachments'] ?? [],
            );

            if ($updateProcessedRegistry) {
                $this->processedMessages->mark($messageId);
            }

            if ($moveFixtureFiles) {
                rename($file, $processedDir.'/'.basename($file));
            }
        }

        return $dtos;
    }

    /**
     * @param array<string, mixed> $imap
     *
     * @return list<IngestTicketDto>
     */
    private function collectPendingRealInbox(array $imap, bool $updateProcessedRegistry): array
    {
        $cm = new \Webklex\PHPIMAP\ClientManager();
        $client = $cm->make([
            'host' => (string) ($imap['host'] ?? ''),
            'port' => (int) ($imap['port'] ?? 993),
            'encryption' => 'ssl',
            'validate_cert' => true,
            'username' => (string) ($imap['user'] ?? ''),
            'password' => (string) ($imap['password'] ?? ''),
            'protocol' => 'imap',
        ]);

        $client->connect();
        $folder = $client->getFolder((string) ($imap['folder_inbox'] ?? 'INBOX'));
        $lookback = $this->pollLookback($imap);
        $messages = $folder->messages()->all()->limit($lookback)->fetchOrderDesc()->get();

        $dtos = [];
        $reservedTicketNumbers = [];
        foreach ($messages as $message) {
            $messageId = $this->resolveMessageId($message);
            if (!$this->shouldIngestMessage($messageId, $updateProcessedRegistry)) {
                continue;
            }

            $ticketNumber = $this->ticketNumbers->generate($reservedTicketNumbers);
            $reservedTicketNumbers[] = $ticketNumber;

            $dtos[] = new IngestTicketDto(
                ticketNumber: $ticketNumber,
                inputSource: 'imap',
                subject: (string) $message->getSubject(),
                body: (string) $message->getTextBody(),
                sourceRef: $messageId,
                rawPayload: [
                    'from' => $message->getFrom()?->first()?->mail,
                    'message_id' => $messageId,
                    'imap_uid' => $message->getUid(),
                ],
                attachments: $this->extractMailAttachments($message),
            );

            if ($updateProcessedRegistry) {
                $this->processedMessages->mark($messageId);
            }
        }

        return $dtos;
    }

    private function shouldIngestMessage(string $messageId, bool $updateProcessedRegistry): bool
    {
        if ('' === $messageId) {
            return false;
        }

        if ($this->processedMessages->has($messageId)) {
            return false;
        }

        $existing = $this->tickets->findOneBy(['sourceRef' => $messageId, 'inputSource' => 'imap']);
        if (null !== $existing) {
            if ($updateProcessedRegistry) {
                $this->processedMessages->mark($messageId);
            }

            return false;
        }

        return true;
    }

    private function resolveMessageId(object $message): string
    {
        $messageId = method_exists($message, 'getMessageId') ? (string) $message->getMessageId() : '';
        if ('' !== $messageId) {
            return $messageId;
        }

        return 'uid:'.hash('sha256', (string) $message->getUid());
    }

    /**
     * @param array<string, mixed> $imap
     */
    private function pollLookback(array $imap): int
    {
        $lookback = (int) ($imap['poll_lookback'] ?? self::DEFAULT_POLL_LOOKBACK);

        return max(1, min(100, $lookback));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractMailAttachments(object $message): array
    {
        if (!method_exists($message, 'getAttachments')) {
            return [];
        }

        $out = [];
        foreach ($message->getAttachments() as $attachment) {
            $content = method_exists($attachment, 'getContent') ? $attachment->getContent() : null;
            if (!is_string($content) || '' === $content) {
                continue;
            }

            $filename = method_exists($attachment, 'getName')
                ? (string) ($attachment->getName() ?: 'piece-jointe')
                : 'piece-jointe';
            $mime = method_exists($attachment, 'getContentType')
                ? (string) ($attachment->getContentType() ?: 'application/octet-stream')
                : 'application/octet-stream';

            $out[] = [
                'filename' => $filename,
                'mime' => $mime,
                'content_base64' => base64_encode($content),
            ];
        }

        return $out;
    }
}
