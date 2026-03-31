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
use RuntimeException;

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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setParams(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();

        Event::assertDispatched(RavenNotificationSent::class, function (RavenNotificationSent $event) {
            return $event->channel === 'EMAIL' &&
                $event->context->name === 'user-created';
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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setParams(['booking_id' => 'JET12345']);

        Notification::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SendGrid API error'));

        try {
            (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();
            $this->fail('Expected exception was not thrown');
        } catch (RavenDeliveryException $e) {
            $this->assertCount(1, $e->getFailures());
            $this->assertStringContainsString('1 of 1 recipients', $e->getMessage());
        }

        Event::assertDispatched(RavenNotificationFailed::class, function (RavenNotificationFailed $event) {
            return $event->channel === 'EMAIL' &&
                $event->context->name === 'user-created' &&
                $event->exception instanceof RavenDeliveryException;
        });

        Event::assertNotDispatched(RavenNotificationSent::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_partial_failure_continues_to_remaining_recipients(): void
    {
        Event::fake();

        $user1 = User::factory()->make([
            'name' => 'User One',
            'email' => 'user1@raven.com',
        ]);

        $user2 = User::factory()->make([
            'name' => 'User Two',
            'email' => 'user2@raven.com',
        ]);

        $user3 = User::factory()->make([
            'name' => 'User Three',
            'email' => 'user3@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user1, $user2, $user3]);
        $scroll->setParams(['booking_id' => 'JET12345']);

        $callCount = 0;
        Notification::shouldReceive('send')
            ->times(3)
            ->andReturnUsing(function ($recipients) use (&$callCount, $user2) {
                $callCount++;
                $recipient = is_array($recipients) ? $recipients[0] : $recipients;
                if ($recipient->email === $user2->email) {
                    throw new RuntimeException('SendGrid API error for user2');
                }
            });

        try {
            (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();
            $this->fail('Expected RavenDeliveryException was not thrown');
        } catch (RavenDeliveryException $e) {
            $failures = $e->getFailures();
            $this->assertCount(1, $failures);
            $this->assertSame($user2, $failures[0]['recipient']);
            $this->assertStringContainsString('1 of 3 recipients', $e->getMessage());
        }

        $this->assertEquals(3, $callCount, 'All 3 recipients should have been attempted');

        Event::assertDispatched(RavenNotificationFailed::class);
        Event::assertNotDispatched(RavenNotificationSent::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_delivery_exception_contains_all_failure_details(): void
    {
        Event::fake();

        $user1 = User::factory()->make([
            'name' => 'User One',
            'email' => 'user1@raven.com',
        ]);

        $user2 = User::factory()->make([
            'name' => 'User Two',
            'email' => 'user2@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user1, $user2]);
        $scroll->setParams(['booking_id' => 'JET12345']);

        Notification::shouldReceive('send')
            ->times(2)
            ->andThrow(new RuntimeException('API error'));

        try {
            (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();
            $this->fail('Expected RavenDeliveryException was not thrown');
        } catch (RavenDeliveryException $e) {
            $failures = $e->getFailures();
            $this->assertCount(2, $failures);
            $this->assertSame($user1, $failures[0]['recipient']);
            $this->assertSame($user2, $failures[1]['recipient']);
            $this->assertInstanceOf(RuntimeException::class, $failures[0]['exception']);
            $this->assertInstanceOf(RuntimeException::class, $failures[1]['exception']);
            $this->assertStringContainsString('2 of 2 recipients', $e->getMessage());
        }
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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients('not-an-email');
        $scroll->setParams(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();

        Event::assertDispatched(RavenNotificationSent::class);
    }
}
