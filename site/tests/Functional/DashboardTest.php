<?php

namespace App\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardTest extends WebTestCase
{
    public function testHomepageIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testPromptCatalogPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/prompts');
        $this->assertResponseIsSuccessful();
    }
}
