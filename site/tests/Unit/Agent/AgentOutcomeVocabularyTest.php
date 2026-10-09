<?php

namespace App\Tests\Unit\Agent;

use App\Agent\AgentOutcomeVocabulary;
use PHPUnit\Framework\TestCase;

final class AgentOutcomeVocabularyTest extends TestCase
{
    public function testHttpProbeRequiresUrl(): void
    {
        $this->expectNotToPerformAssertions();

        AgentOutcomeVocabulary::assertTriageOutcome([
            'resolution' => 'needs_investigation',
            'service_request' => [
                'service' => 'http_probe',
                'params' => [
                    'url' => 'https://caladair.com/',
                    'purpose' => 'homepage',
                ],
            ],
        ]);
    }

    public function testRequestHumanActionRequiresHumanActionParam(): void
    {
        $this->expectNotToPerformAssertions();

        AgentOutcomeVocabulary::assertTriageOutcome([
            'resolution' => 'needs_human',
            'service_request' => [
                'service' => 'request_human_action',
                'params' => [
                    'human_action' => 'test_form_submission',
                ],
            ],
        ]);
    }

    public function testHttpProbeRejectsEmptyUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AgentOutcomeVocabulary::assertTriageOutcome([
            'resolution' => 'needs_investigation',
            'service_request' => [
                'service' => 'http_probe',
                'params' => [
                    'url' => '',
                    'purpose' => 'homepage',
                ],
            ],
        ]);
    }

    public function testHttpProbeBatchRequiresUrls(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        AgentOutcomeVocabulary::assertTriageOutcome([
            'resolution' => 'needs_investigation',
            'service_request' => [
                'service' => 'http_probe_batch',
                'params' => ['urls' => []],
            ],
        ]);
    }
}
