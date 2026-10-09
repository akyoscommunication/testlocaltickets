<?php

namespace App\Tests\Unit\Agent;

use App\Agent\AgentRunErrorFormatter;
use PHPUnit\Framework\TestCase;

final class AgentRunErrorFormatterTest extends TestCase
{
    public function testSummarizeExtractsCodexApiError(): void
    {
        $stderr = <<<'TXT'
Reading additional input from stdin...
ERROR: {
  "type": "error",
  "error": {
    "type": "invalid_request_error",
    "code": "invalid_json_schema",
    "message": "Invalid schema for response_format"
  },
  "status": 400
}
TXT;

        $summary = AgentRunErrorFormatter::summarize($stderr, 1);

        self::assertStringContainsString('[invalid_json_schema]', $summary);
        self::assertStringContainsString('Invalid schema', $summary);
        self::assertStringContainsString('HTTP 400', $summary);
    }

    public function testSummarizeFallsBackToExitCode(): void
    {
        self::assertSame(
            'code sortie 2 (pas de stderr)',
            AgentRunErrorFormatter::summarize('', 2),
        );
    }

    public function testSummarizeDoesNotReturnLoneBrace(): void
    {
        $stderr = <<<'TXT'
codex exec a échoué (code 1)
ERROR: {
  "type": "error",
  "error": {
    "code": "invalid_json_schema",
    "message": "Missing project_key"
  },
  "status": 400
}
}
TXT;

        $summary = AgentRunErrorFormatter::summarize($stderr, 1);

        self::assertNotSame('}', $summary);
        self::assertStringContainsString('[invalid_json_schema]', $summary);
        self::assertStringContainsString('Missing project_key', $summary);
    }

    public function testSummarizeIgnoresCodexTokenFooter(): void
    {
        $stderr = <<<'TXT'
{"schema_version":"2","phase":"triage"}
tokens used
12 270
Sortie JSON invalide ou incomplète pour la phase triage : /tmp/out.json
TXT;

        $summary = AgentRunErrorFormatter::summarize($stderr, 1);

        self::assertStringContainsString('Sortie JSON invalide', $summary);
        self::assertStringNotContainsString('12', $summary);
    }
}
