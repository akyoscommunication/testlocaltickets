<?php

namespace App\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WebklexImapPackageTest extends TestCase
{
    public function testPhpImapClientIsAvailable(): void
    {
        $this->assertTrue(class_exists(\Webklex\PHPIMAP\ClientManager::class));
    }
}
