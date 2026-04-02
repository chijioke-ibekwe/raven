<?php

namespace ChijiokeIbekwe\Raven\Tests\Unit;

use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use Exception;
use Illuminate\Notifications\Notification;
use Mockery;

class AmazonSesChannelTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'ses');
    }

    public function test_that_exception_is_thrown_when_a_non_email_notification_sender_is_passed(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AmazonSesChannel requires an EmailNotification notification');

        $this->app->instance(SesClient::class, Mockery::mock(SesClient::class));

        $channel = new AmazonSesChannel;
        $channel->send(null, Mockery::mock(Notification::class));
    }
}
