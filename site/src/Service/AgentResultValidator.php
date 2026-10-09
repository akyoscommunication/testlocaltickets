<?php

namespace App\Service;

use App\Agent\AgentOutcomeVocabulary;

final class AgentResultValidator
{
    /**
     * @return array<string, mixed>
     */
    public function validateAndParse(string $json, string $expectedPhase): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('La sortie agent doit être un objet JSON.');
        }

        $required = match ($expectedPhase) {
            'triage' => ['schema_version', 'ticket_number', 'phase', 'resolution', 'service_request'],
            default => ['schema_version', 'ticket_number', 'phase', 'resolution', 'next_step'],
        };

        foreach ($required as $field) {
            if (!array_key_exists($field, $data)) {
                throw new \InvalidArgumentException(sprintf('Champ manquant : %s', $field));
            }
        }

        if (($data['phase'] ?? '') !== $expectedPhase) {
            throw new \InvalidArgumentException(sprintf(
                'Phase attendue %s, reçue %s',
                $expectedPhase,
                $data['phase'] ?? 'null'
            ));
        }

        if ('triage' === $expectedPhase && '2' !== ($data['schema_version'] ?? '')) {
            throw new \InvalidArgumentException('Triage attend schema_version=2 avec service_request.');
        }

        match ($expectedPhase) {
            'triage' => AgentOutcomeVocabulary::assertTriageOutcome($data),
            'code_analysis' => AgentOutcomeVocabulary::assertCodeOutcome($data),
            default => null,
        };

        return $data;
    }
}
