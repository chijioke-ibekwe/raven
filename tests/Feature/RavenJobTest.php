<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\Raven;
use ChijiokeIbekwe\Raven\Jobs\RavenChannelJob;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Bus;

class RavenJobTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'sendgrid');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_notification_context_name_is_not_provided_in_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification context name is not set');
        $this->expectExceptionCode(422);

        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        $scroll = Scroll::make()
            ->to($user)
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_notification_context_name_does_not_exist_in_config()
    {
        $this->expectException(RavenContextNotFoundException::class);
        $this->expectExceptionMessage('Notification context with name user-verified does not exist');
        $this->expectExceptionCode(404);

        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        $scroll = Scroll::make()
            ->for('user-verified')
            ->to($user)
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_recipients_are_not_provided_in_notification_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not set');
        $this->expectExceptionCode(422);

        Bus::fake();

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_a_non_notifiable_recipient_is_provided_in_notification_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not a notifiable');
        $this->expectExceptionCode(422);

        Bus::fake();

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($context)
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_notification_is_not_sent_when_notification_context_is_inactive()
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => false,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with([
                'booking_id' => 'JET12345',
            ]);

        (new Raven($scroll))->handle();

        Bus::assertNotDispatched(RavenChannelJob::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_a_notification_context_has_an_invalid_channel()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification context has an invalid channel: em');

        Bus::fake();

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['em'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_channel_job_is_dispatched_when_active_key_is_absent_from_context_config(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['EMAIL'],
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL &&
                $job->context->name === 'user-created';
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_no_channel_job_is_dispatched_when_context_has_empty_channels(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => [],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertNotDispatched(RavenChannelJob::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_channel_jobs_are_dispatched_for_each_channel(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'sms_template_filename' => 'user-created.txt',
            'in_app_template_filename' => 'user-created.json',
            'channels' => ['EMAIL', 'SMS', 'DATABASE'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, 3);

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::SMS;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::DATABASE;
        });
    }
}
