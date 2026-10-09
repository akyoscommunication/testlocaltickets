<?php

namespace App\Tests\Unit;

use App\Service\AgentResultValidator;
use PHPUnit\Framework\TestCase;

final class AgentResultValidatorTest extends TestCase
{
    private AgentResultValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new AgentResultValidator();
    }

    public function testValidTriageJson(): void
    {
        $json = file_get_contents(dirname(__DIR__).'/fixtures/agent/triage_close.json');
        $data = $this->validator->validateAndParse($json, 'triage');

        $this->assertSame('close_ticket', $data['service_request']['service']);
        $this->assertSame('triage', $data['phase']);
    }

    public function testRejectsMissingField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateAndParse('{"phase":"triage"}', 'triage');
    }

    public function testRejectsWrongPhase(): void
    {
        $json = file_get_contents(dirname(__DIR__).'/fixtures/agent/triage_close.json');
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validateAndParse($json, 'code_analysis');
    }
}
