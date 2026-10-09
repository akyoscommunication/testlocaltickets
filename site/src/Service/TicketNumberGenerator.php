<?php

namespace App\Service;

use App\Repository\TicketProcessingRepository;

final class TicketNumberGenerator
{
    public function __construct(
        private readonly string $prefix,
        private readonly TicketProcessingRepository $tickets,
    ) {
    }

    /**
     * @param list<string> $reserved numéros déjà attribués dans le même batch (poll IMAP, etc.)
     */
    public function generate(array $reserved = []): string
    {
        $date = (new \DateTimeImmutable())->format('Ymd');
        $base = sprintf('%s-%s', $this->prefix, $date);

        for ($seq = 1; $seq < 10000; ++$seq) {
            $candidate = sprintf('%s-%04d', $base, $seq);
            if (in_array($candidate, $reserved, true)) {
                continue;
            }
            if (null === $this->tickets->findOneBy(['ticketNumber' => $candidate])) {
                return $candidate;
            }
        }

        throw new \RuntimeException('Impossible de générer un ticket_number unique.');
    }
}
