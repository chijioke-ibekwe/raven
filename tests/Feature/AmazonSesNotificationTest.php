<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\RavenChannelJob;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Notification;

class AmazonSesNotificationTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'ses');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    protected function setUp(): void
    {
        parent::setUp();

        $emailDir = resource_path('templates/email');
        if (! is_dir($emailDir)) {
            mkdir($emailDir, 0755, true);
        }
        file_put_contents($emailDir.'/user-verified.html', '<p>Hello {{name}}</p>');
    }

    protected function tearDown(): void
    {
        $templateFile = resource_path('templates/email/user-verified.html');
        if (file_exists($templateFile)) {
            unlink($templateFile);
        }

        parent::tearDown();
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
            'email_template_filename' => 'user-verified.html',
            'email_subject' => 'You have been verified',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-verified', config('notification-contexts.user-verified'));

        $scroll = Scroll::make()
            ->for('user-verified')
            ->to($user)
            ->with(['name' => 'John Doe']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotification::class,
            function (EmailNotification $notification) use ($user) {
                return $notification->notificationContext->name === 'user-verified' &&
                    $notification->via($user) === ['ses'];
            }
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_ses_context_has_no_email_template_filename(): void
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-verified has no email template id or template file name');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-verified', [
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-verified', config('notification-contexts.user-verified'));

        $scroll = Scroll::make()
            ->for('user-verified')
            ->to($user)
            ->with(['name' => 'John Doe']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
    }
}
