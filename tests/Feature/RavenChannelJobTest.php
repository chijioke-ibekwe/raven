<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Jobs\RavenChannelJob;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;

class RavenChannelJobTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'sendgrid');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    /**
     * @throws \Throwable
     */
    public function test_that_sent_event_is_dispatched_on_success(): void
    {
        Notification::fake();
        Event::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();

        Event::assertDispatched(RavenNotificationSent::class, function (RavenNotificationSent $event) use ($user) {
            return $event->channel === 'EMAIL' &&
                $event->context->name === 'user-created' &&
                $event->recipient === $user;
        });

        Event::assertNotDispatched(RavenNotificationFailed::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_failed_event_is_dispatched_on_failure(): void
    {
        Event::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RavenDeliveryException('SendGrid API error'));

        try {
            (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
            $this->fail('Expected exception was not thrown');
        } catch (RavenDeliveryException $e) {
            $this->assertEquals('SendGrid API error', $e->getMessage());
        }

        Event::assertDispatched(RavenNotificationFailed::class, function (RavenNotificationFailed $event) use ($user) {
            return $event->channel === 'EMAIL' &&
                $event->context->name === 'user-created' &&
                $event->recipient === $user &&
                $event->exception instanceof RavenDeliveryException;
        });

        Event::assertNotDispatched(RavenNotificationSent::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_invalid_email_string_is_skipped_without_exception(): void
    {
        Notification::fake();
        Event::fake();

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('not-an-email')
            ->with(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'not-an-email'))->handle();

        Event::assertNotDispatched(RavenNotificationSent::class);
    }

    public function test_that_per_channel_queue_config_is_applied(): void
    {
        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
            'queue' => [
                'email' => ['queue' => 'critical', 'connection' => 'sqs'],
            ],
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with(['booking_id' => 'JET12345']);

        $job = new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'john.doe@raven.com');

        $this->assertEquals('critical', $job->queue);
        $this->assertEquals('sqs', $job->connection);
    }

    public function test_that_global_fallback_is_used_when_context_has_no_queue_config(): void
    {
        config()->set('raven.customizations.queue_name', 'notifications');
        config()->set('raven.customizations.queue_connection', 'redis');

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with(['booking_id' => 'JET12345']);

        $job = new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'john.doe@raven.com');

        $this->assertEquals('notifications', $job->queue);
        $this->assertEquals('redis', $job->connection);
    }

    public function test_that_partial_queue_config_falls_back_to_global_for_unconfigured_channels(): void
    {
        config()->set('raven.customizations.queue_name', 'default-queue');
        config()->set('raven.customizations.queue_connection', 'redis');

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'sms_template_filename' => 'user-created.txt',
            'channels' => ['email', 'sms'],
            'active' => true,
            'queue' => [
                'email' => ['queue' => 'critical', 'connection' => 'sqs'],
            ],
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with(['booking_id' => 'JET12345']);

        $emailJob = new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'john.doe@raven.com');
        $this->assertEquals('critical', $emailJob->queue);
        $this->assertEquals('sqs', $emailJob->connection);

        $smsJob = new RavenChannelJob($scroll, $context, ChannelType::SMS, 'john.doe@raven.com');
        $this->assertEquals('default-queue', $smsJob->queue);
        $this->assertEquals('redis', $smsJob->connection);
    }

    public function test_that_existing_queue_name_config_still_works_as_fallback(): void
    {
        config()->set('raven.customizations.queue_name', 'legacy-queue');

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with(['booking_id' => 'JET12345']);

        $job = new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'john.doe@raven.com');

        $this->assertEquals('legacy-queue', $job->queue);
    }
}
