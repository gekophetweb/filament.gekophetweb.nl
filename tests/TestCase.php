<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();

        // Keep test log output out of storage/logs.
        config(['logging.channels.blocked_ips' => ['driver' => 'single', 'path' => sys_get_temp_dir().'/blocked-ips-test.log']]);
        config(['logging.channels.mcp_audit' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]);
    }
}
