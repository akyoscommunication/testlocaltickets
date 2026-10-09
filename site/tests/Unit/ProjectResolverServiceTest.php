<?php

namespace App\Tests\Unit;

use App\Service\ProjectResolverService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectResolverServiceTest extends TestCase
{
    private ProjectResolverService $resolver;

    protected function setUp(): void
    {
        $this->resolver = new ProjectResolverService([
            'client-site' => ['domains' => ['client.fr', 'www.client.fr']],
            'client-b2b' => ['domains' => ['b2b.client.fr']],
        ]);
    }

    public function testAutoResolveWhenHighConfidence(): void
    {
        $result = $this->resolver->resolve([
            'project_key' => 'client-site',
            'confidence' => 0.9,
        ]);

        $this->assertSame('auto', $result['status']);
        $this->assertSame('client-site', $result['project_key']);
    }

    public function testAmbiguousWhenTwoDomainsMatch(): void
    {
        $result = $this->resolver->resolve(
            null,
            'user@unknown.com',
            'Contact sur client.fr et b2b.client.fr',
        );

        $this->assertSame('ambiguous', $result['status']);
        $this->assertNull($result['project_key']);
        $this->assertCount(2, $result['candidates']);
    }

    public function testSingleCandidateFromSenderDomain(): void
    {
        $result = $this->resolver->resolve(null, 'support@client.fr', '');

        $this->assertSame('auto', $result['status']);
        $this->assertSame('client-site', $result['project_key']);
    }

    #[DataProvider('invalidKeysProvider')]
    public function testIsValidProjectKey(string $key, bool $expected): void
    {
        $this->assertSame($expected, $this->resolver->isValidProjectKey($key));
    }

    public static function invalidKeysProvider(): iterable
    {
        yield ['client-site', true];
        yield ['unknown', false];
        yield ['', false];
    }
}
