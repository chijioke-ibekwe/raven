<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\EncryptedRavenChannelJob;
use ChijiokeIbekwe\Raven\Jobs\Raven;
use ChijiokeIbekwe\Raven\Jobs\RavenChannelJob;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
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
            'channels' => ['email'],
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
            'channels' => ['email'],
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
            'channels' => ['email'],
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
            'channels' => ['email'],
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
    public function test_that_scroll_channel_override_dispatches_only_overridden_channels(): void
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
            'channels' => ['email', 'sms', 'database'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->channels(['email'])
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, 1);

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });

        Bus::assertNotDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::SMS;
        });

        Bus::assertNotDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::DATABASE;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_context_channels_are_used_when_no_scroll_channel_override(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'sms_template_filename' => 'user-created.txt',
            'channels' => ['email', 'sms'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, 2);

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::SMS;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_sync_dispatch_runs_job_synchronously(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->sync()
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatchedSync(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_async_dispatch_is_used_when_sync_is_not_set(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });

        Bus::assertNotDispatchedSync(RavenChannelJob::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_after_commit_dispatch_chains_after_commit_on_pending_dispatch(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->afterCommit()
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL
                && $job->afterCommit === true;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_before_commit_dispatch_chains_before_commit_on_pending_dispatch(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->beforeCommit()
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL
                && $job->afterCommit === false;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_single_delay_is_applied_to_all_channel_jobs(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'sms_template_filename' => 'user-created.txt',
            'channels' => ['email', 'sms'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->delay(60)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL
                && $job->delay === 60;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::SMS
                && $job->delay === 60;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_per_channel_delay_is_applied_to_respective_channel_jobs(): void
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
            'channels' => ['email', 'sms', 'database'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->delay(['email' => 120, 'sms' => 30])
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL
                && $job->delay === 120;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::SMS
                && $job->delay === 30;
        });

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::DATABASE
                && is_null($job->delay);
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_no_delay_is_applied_when_delay_is_not_set(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL
                && is_null($job->delay);
        });
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
            'channels' => ['email', 'sms', 'database'],
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

    /**
     * @throws \Throwable
     */
    public function test_that_encrypted_context_dispatches_encrypted_channel_job(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
            'encrypted' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(EncryptedRavenChannelJob::class, function (EncryptedRavenChannelJob $job) {
            return $job->channelType === ChannelType::EMAIL;
        });

        Bus::assertNotDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return ! $job instanceof EncryptedRavenChannelJob;
        });
    }

    /**
     * @throws \Throwable
     */
    public function test_that_non_encrypted_context_dispatches_regular_channel_job(): void
    {
        Bus::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
        ]);

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->with(['booking_id' => 'JET12345']);

        (new Raven($scroll))->handle();

        Bus::assertDispatched(RavenChannelJob::class, function (RavenChannelJob $job) {
            return ! $job instanceof EncryptedRavenChannelJob
                && $job->channelType === ChannelType::EMAIL;
        });
    }

    public function test_that_encrypted_channel_job_implements_should_be_encrypted(): void
    {
        $this->assertTrue(
            in_array(ShouldBeEncrypted::class, class_implements(EncryptedRavenChannelJob::class))
        );
    }

    public function test_that_encrypted_channel_job_inherits_queue_resolution(): void
    {
        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'sendgrid-template',
            'channels' => ['email'],
            'active' => true,
            'encrypted' => true,
            'queue' => [
                'email' => ['queue' => 'secure', 'connection' => 'sqs'],
            ],
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to('john.doe@raven.com')
            ->with(['booking_id' => 'JET12345']);

        $job = new EncryptedRavenChannelJob($scroll, $context, ChannelType::EMAIL);

        $this->assertEquals('secure', $job->queue);
        $this->assertEquals('sqs', $job->connection);
    }
}
