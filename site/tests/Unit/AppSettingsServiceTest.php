<?php

namespace App\Tests\Unit;

use App\Entity\AppConfiguration;
use App\Repository\AppConfigurationRepository;
use App\Service\AppSettingsService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class AppSettingsServiceTest extends TestCase
{
    public function testDefaultsWhenNothingStored(): void
    {
        $repo = $this->createMock(AppConfigurationRepository::class);
        $repo->method('getSingleton')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('https://example.test/settings'));

        $defaults = [
            'imap' => ['poll_enabled' => false, 'host' => 'imap.example.com'],
            'webhook' => ['enabled' => true, 'secret' => 'secret-test'],
            'agent' => ['profile' => 'fixture_default'],
        ];

        $service = new AppSettingsService($repo, $em, $requestStack, $defaults);

        self::assertFalse($service->isImapPollEnabled());
        self::assertSame('fixture_default', $service->getAgentProfile());
        self::assertSame('secret-test', $service->getWebhookSecret());
        self::assertSame('https://example.test/api/v1/tickets/ingest', $service->getWebhookIngestUrl());
    }

    public function testSaveKeepsPasswordWhenBlank(): void
    {
        $entity = new AppConfiguration();
        $entity->setSettings([
            'imap' => ['password' => 'stored-secret'],
        ]);

        $repo = $this->createMock(AppConfigurationRepository::class);
        $repo->method('getSingleton')->willReturn($entity);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $requestStack = new RequestStack();
        $defaults = [
            'imap' => ['poll_enabled' => false, 'password' => ''],
            'webhook' => ['enabled' => true, 'secret' => ''],
            'agent' => ['profile' => 'fixture_default'],
        ];

        $service = new AppSettingsService($repo, $em, $requestStack, $defaults);
        $service->save([
            'imap' => [
                'poll_enabled' => true,
                'password' => '',
            ],
        ]);

        self::assertTrue($service->isImapPollEnabled());
        self::assertSame('stored-secret', $service->get('imap.password'));
    }
}
