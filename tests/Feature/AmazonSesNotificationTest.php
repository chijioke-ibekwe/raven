<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\Raven;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Notification;

class AmazonSesNotificationTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'ses');
        config()->set('raven.providers.ses.template_source', 'sendgrid');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_dispatched_when_raven_listener_receives_a_ses_context(): void
    {
        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-verified', [
            'email_template_id' => 'ses-sendgrid-template',
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-verified');
        $scroll->setRecipients($user);
        $scroll->setParams(['name' => 'John Doe']);

        (new Raven($scroll))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotificationSender::class,
            function (EmailNotificationSender $notification) use ($user) {
                return $notification->notificationContext->name === 'user-verified' &&
                    $notification->via($user) === ['ses'];
            }
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_ses_context_with_sendgrid_source_has_no_email_template_id(): void
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-verified has no email template id');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-verified', [
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-verified');
        $scroll->setRecipients($user);
        $scroll->setParams(['name' => 'John Doe']);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_ses_context_with_filesystem_source_has_no_email_template_filename(): void
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-verified has no email template file name');
        $this->expectExceptionCode(422);

        config()->set('raven.providers.ses.template_source', 'filesystem');

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-verified', [
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-verified');
        $scroll->setRecipients($user);
        $scroll->setParams(['name' => 'John Doe']);

        (new Raven($scroll))->handle();
    }
}
