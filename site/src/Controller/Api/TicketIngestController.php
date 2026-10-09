<?php

namespace App\Controller\Api;

use App\Dto\IngestTicketDto;
use App\Message\IngestTicketMessage;
use App\Repository\TicketProcessingRepository;
use App\Service\AppSettingsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/tickets')]
final class TicketIngestController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $bus,
        private readonly TicketProcessingRepository $tickets,
        private readonly AppSettingsService $settings,
    ) {
    }

    #[Route('/ingest', name: 'api_ticket_ingest', methods: ['POST'])]
    public function ingest(Request $request): JsonResponse
    {
        if (!$this->settings->isWebhookEnabled()) {
            return new JsonResponse(['error' => 'Webhook ingest disabled'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $auth = $request->headers->get('Authorization', '');
        if ($auth !== 'Bearer '.$this->settings->getWebhookSecret()) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], Response::HTTP_BAD_REQUEST);
        }

        $ticketNumber = $data['ticket_number'] ?? null;
        if (!is_string($ticketNumber) || '' === trim($ticketNumber)) {
            return new JsonResponse(['error' => 'ticket_number required'], Response::HTTP_BAD_REQUEST);
        }

        $sourceRef = $data['delivery_id'] ?? $data['source_ref'] ?? hash('sha256', $request->getContent());

        if ($this->tickets->findOneBy(['ticketNumber' => $ticketNumber])) {
            return new JsonResponse([
                'status' => 'accepted',
                'ticket_number' => $ticketNumber,
                'message' => 'already_exists',
            ], Response::HTTP_ACCEPTED);
        }

        $dto = new IngestTicketDto(
            ticketNumber: $ticketNumber,
            inputSource: 'webhook',
            subject: (string) ($data['subject'] ?? ''),
            body: (string) ($data['body'] ?? ''),
            sourceRef: is_string($sourceRef) ? $sourceRef : null,
            targetNodeId: isset($data['target_node_id']) ? (string) $data['target_node_id'] : null,
            rawPayload: $data,
            attachments: is_array($data['attachments'] ?? null) ? $data['attachments'] : [],
        );

        $this->bus->dispatch(new IngestTicketMessage($dto));

        return new JsonResponse([
            'status' => 'accepted',
            'ticket_number' => $ticketNumber,
        ], Response::HTTP_ACCEPTED);
    }
}
