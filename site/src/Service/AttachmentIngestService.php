<?php

namespace App\Service;

use App\FileStorage\TicketFileStorage;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AttachmentIngestService
{
    /** @var list<string> */
    private const ALLOWED_MIME_PREFIXES = ['image/', 'text/', 'application/pdf'];

    public function __construct(
        private readonly TicketFileStorage $files,
        #[Autowire('%env(int:ATTACHMENT_MAX_BYTES)%')]
        private readonly int $maxBytes,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $attachments
     *
     * @return list<array{path: string, filename: string, mime: string, size: int}>
     */
    public function persist(string $ticketNumber, array $attachments): array
    {
        $saved = [];

        foreach ($attachments as $index => $attachment) {
            if (!is_array($attachment)) {
                continue;
            }

            $encoded = $attachment['content_base64'] ?? $attachment['data'] ?? null;
            if (!is_string($encoded) || '' === $encoded) {
                continue;
            }

            $binary = base64_decode($encoded, true);
            if (false === $binary || '' === $binary) {
                continue;
            }

            $size = strlen($binary);
            if ($size > $this->maxBytes) {
                continue;
            }

            $mime = (string) ($attachment['mime'] ?? $attachment['content_type'] ?? 'application/octet-stream');
            if (!$this->isAllowedMime($mime)) {
                continue;
            }

            $filename = $this->sanitizeFilename((string) ($attachment['filename'] ?? $attachment['name'] ?? ''), $index);
            $relativePath = 'attachments/'.$filename;
            $this->files->write($ticketNumber, $relativePath, $binary);

            $saved[] = [
                'path' => $relativePath,
                'filename' => $filename,
                'mime' => $mime,
                'size' => $size,
            ];
        }

        return $saved;
    }

    private function isAllowedMime(string $mime): bool
    {
        foreach (self::ALLOWED_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }

        return 'application/json' === $mime;
    }

    private function sanitizeFilename(string $filename, int $index): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?? '';
        if ('' === $filename) {
            return sprintf('attachment-%d.bin', $index);
        }

        return $filename;
    }
}
