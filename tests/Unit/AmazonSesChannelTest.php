<?php

namespace ChijiokeIbekwe\Raven\Tests\Unit;

use Aws\Ses\SesClient;
use ChijiokeIbekwe\Raven\Channels\AmazonSesChannel;
use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Exception;
use Illuminate\Notifications\Notification;
use Mockery;
use SendGrid;

class AmazonSesChannelTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'ses');
        config()->set('raven.providers.ses.template_source', 'sendgrid');
    }

    public function test_that_exception_is_thrown_when_a_non_email_notification_sender_is_passed(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('AmazonSesChannel requires an EmailNotificationSender notification');

        $this->app->instance(SesClient::class, Mockery::mock(SesClient::class));
        $this->app->instance(SendGrid::class, Mockery::mock(SendGrid::class));

        $channel = new AmazonSesChannel;
        $channel->send(null, Mockery::mock(Notification::class));
    }

    public function test_that_exception_is_thrown_when_template_source_is_not_sendgrid(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Template source filesystem not currently supported');

        config()->set('raven.providers.ses.template_source', 'filesystem');
        config()->set('raven.customizations.mail.from.address', 'hello@example.com');
        config()->set('raven.customizations.mail.from.name', 'Example');

        $this->app->instance(SesClient::class, Mockery::mock(SesClient::class));
        $this->app->instance(SendGrid::class, Mockery::mock(SendGrid::class));

        $context = NotificationContext::fromConfig('user-verified', [
            'email_template_filename' => 'user-verified.html',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-verified');

        $user = User::factory()->make(['email' => 'john.doe@raven.com']);

        $channel = new AmazonSesChannel;
        $channel->send($user, new EmailNotificationSender($scroll, $context));
    }
}
