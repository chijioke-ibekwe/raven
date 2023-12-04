<?php

namespace ChijiokeIbekwe\Messenger\Tests;

use ChijiokeIbekwe\Messenger\MessengerServiceProvider;

class TestCase extends \Orchestra\Testbench\TestCase
{
    public function setUp(): void
    {
        parent::setUp();
        // additional setup
    }

    protected function getPackageProviders($app): array
    {
        return [
            MessengerServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'test-db');
        $app['config']->set('database.connections.test-db', [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);
    }
}