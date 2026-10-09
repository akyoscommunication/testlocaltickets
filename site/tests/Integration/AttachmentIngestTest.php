<?php

namespace App\Tests\Integration;

use App\Dto\IngestTicketDto;
use App\FileStorage\TicketFileStorage;
use App\Message\IngestTicketMessage;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

final class AttachmentIngestTest extends KernelTestCase
{
    public function testWebhookIngestPersistsBase64Attachment(): void
    {
        self::bootKernel();
        $bus = self::getContainer()->get(MessageBusInterface::class);
        $files = self::getContainer()->get(TicketFileStorage::class);

        $number = 'TKT-ATT-'.substr(uniqid(), -6);
        $binary = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        );
        $this->assertNotFalse($binary);

        $bus->dispatch(new IngestTicketMessage(new IngestTicketDto(
            ticketNumber: $number,
            inputSource: 'webhook',
            subject: 'PJ',
            body: 'Voir capture',
            sourceRef: 'att-'.uniqid(),
            attachments: [
                [
                    'filename' => 'pixel.png',
                    'content_base64' => base64_encode($binary),
                    'mime' => 'image/png',
                ],
            ],
        )));

        $this->assertTrue($files->exists($number, 'attachments/pixel.png'));
        $this->assertSame($binary, $files->read($number, 'attachments/pixel.png'));
    }
}
