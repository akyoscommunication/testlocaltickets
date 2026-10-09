<?php

namespace App\Tests\Unit;

use App\Repository\TicketProcessingRepository;
use App\Service\TicketNumberGenerator;
use PHPUnit\Framework\TestCase;

final class TicketNumberGeneratorTest extends TestCase
{
    public function testGenerateRespectsReservedNumbersInSameBatch(): void
    {
        $repo = $this->createMock(TicketProcessingRepository::class);
        $repo->method('findOneBy')->willReturn(null);

        $generator = new TicketNumberGenerator('TKT', $repo);

        $first = $generator->generate();
        $second = $generator->generate([$first]);

        $this->assertNotSame($first, $second);
        $this->assertMatchesRegularExpression('/^TKT-\d{8}-0002$/', $second);
    }
}
