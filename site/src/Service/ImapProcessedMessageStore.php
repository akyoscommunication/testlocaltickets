<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registre local des Message-ID IMAP déjà pris en charge par cet outil
 * (indépendant du flag \\Seen sur le serveur).
 */
final class ImapProcessedMessageStore
{
    private const MAX_IDS = 2000;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function has(string $messageId): bool
    {
        return in_array($messageId, $this->readIds(), true);
    }

    public function mark(string $messageId): void
    {
        if ('' === $messageId) {
            return;
        }

        $ids = $this->readIds();
        if (in_array($messageId, $ids, true)) {
            return;
        }

        $ids[] = $messageId;
        if (count($ids) > self::MAX_IDS) {
            $ids = array_slice($ids, -self::MAX_IDS);
        }

        $this->writeIds($ids);
    }

    /** @return list<string> */
    private function readIds(): array
    {
        $path = $this->getPath();
        if (!is_file($path)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($path), true);
        if (!is_array($data) || !isset($data['ids']) || !is_array($data['ids'])) {
            return [];
        }

        return array_values(array_filter(
            $data['ids'],
            static fn (mixed $id): bool => is_string($id) && '' !== $id,
        ));
    }

    /** @param list<string> $ids */
    private function writeIds(array $ids): void
    {
        $path = $this->getPath();
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $payload = json_encode([
            'ids' => array_values($ids),
            'updated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($path, $payload, LOCK_EX);
    }

    private function getPath(): string
    {
        return $this->projectDir.'/var/state/imap-processed-ids.json';
    }
}
