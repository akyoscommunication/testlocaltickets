<?php

namespace App\Service;

use App\Agent\AgentOutcomeVocabulary;
use App\Entity\TicketProcessing;
use App\Workflow\TicketWorkflow;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class HumanActionNotifier
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AutomationLogger $logger,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function notify(TicketProcessing $ticket, string $humanAction, string $summary, array $payload): void
    {
        $callbackUrl = $this->resolveCallbackUrl($payload);
        if (null === $callbackUrl) {
            $this->logger->info(
                'Action humaine sans callback HTTP',
                $ticket->getTicketNumber(),
                null,
                ['human_action' => $humanAction, 'input_source' => $ticket->getInputSource()],
            );

            return;
        }

        try {
            $response = $this->httpClient->request('POST', $callbackUrl, [
                'headers' => ['Content-Type' => 'application/json'],
                'json' => [
                    'ticket_number' => $ticket->getTicketNumber(),
                    'human_action' => $humanAction,
                    'human_action_label' => AgentOutcomeVocabulary::humanActionLabel($humanAction),
                    'summary' => $summary,
                    'status' => TicketWorkflow::STATUS_WAITING_FOR_HUMAN,
                ],
                'timeout' => 10,
            ]);
            $this->logger->info(
                sprintf('Callback action humaine HTTP %d', $response->getStatusCode()),
                $ticket->getTicketNumber(),
                null,
                ['callback_url' => $callbackUrl],
            );
        } catch (\Throwable $e) {
            $this->logger->error(
                'Callback action humaine échoué : '.$e->getMessage(),
                $ticket->getTicketNumber(),
                null,
                ['callback_url' => $callbackUrl],
            );
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function resolveCallbackUrl(array $payload): ?string
    {
        foreach (['callback_url', 'callbackUrl'] as $key) {
            if (isset($payload[$key]) && is_string($payload[$key]) && '' !== trim($payload[$key])) {
                return trim($payload[$key]);
            }
        }

        $raw = $payload['raw'] ?? $payload['rawPayload'] ?? null;
        if (is_array($raw)) {
            foreach (['callback_url', 'callbackUrl'] as $key) {
                if (isset($raw[$key]) && is_string($raw[$key]) && '' !== trim($raw[$key])) {
                    return trim($raw[$key]);
                }
            }
        }

        return null;
    }
}
