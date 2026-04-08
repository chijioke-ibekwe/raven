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
use PHPUnit\Framework\Attributes\DataProvider;

class EmailNotificationTest extends TestCase
{
    public static function emailProviders(): array
    {
        return [
            'sendgrid' => ['sendgrid'],
            'ses' => ['ses'],
            'postmark' => ['postmark'],
            'mailgun' => ['mailgun'],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('raven.customizations.templates_directory', resource_path('templates'));

        $emailDir = resource_path('templates/email');
        if (! is_dir($emailDir)) {
            mkdir($emailDir, 0755, true);
        }
        file_put_contents($emailDir.'/welcome.html', '<p>Welcome {{name}}</p>');
    }

    protected function tearDown(): void
    {
        $templateFile = resource_path('templates/email/welcome.html');
        if (file_exists($templateFile)) {
            unlink($templateFile);
        }

        parent::tearDown();
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('emailProviders')]
    public function test_dispatches_email_notification_with_provider_hosted_template(string $provider): void
    {
        config()->set('raven.default.email', $provider);
        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'remote-template-id',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to($user)
            ->cc(['email@raven.com' => 'Jane Doe'])
            ->with(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotification::class,
            fn (EmailNotification $notification) => $notification->scroll === $scroll
                && $notification->notificationContext->name === 'user-created'
                && $notification->notificationContext->email_template_id === 'remote-template-id'
                && $notification->via($user) === [$provider],
        );
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('emailProviders')]
    public function test_dispatches_email_notification_with_filesystem_template(string $provider): void
    {
        config()->set('raven.default.email', $provider);
        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.welcome', [
            'email_template_filename' => 'welcome.html',
            'email_subject' => 'Welcome, {{name}}!',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('welcome', config('notification-contexts.welcome'));

        $scroll = Scroll::make()
            ->for('welcome')
            ->to($user)
            ->with(['name' => 'John Doe']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotification::class,
            fn (EmailNotification $notification) => $notification->scroll === $scroll
                && $notification->notificationContext->name === 'welcome'
                && $notification->via($user) === [$provider],
        );
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('emailProviders')]
    public function test_dispatches_email_notification_to_on_demand_recipient(string $provider): void
    {
        config()->set('raven.default.email', $provider);
        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-created', [
            'email_template_id' => 'remote-template-id',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = Scroll::make()
            ->for('user-created')
            ->to([$user, 'jane.doe@raven.com'])
            ->with(['booking_id' => 'JET12345']);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'jane.doe@raven.com'))->handle();

        Notification::assertSentTo($user, EmailNotification::class);
        Notification::assertSentOnDemand(EmailNotification::class);
    }

    /**
     * @throws \Throwable
     */
    #[DataProvider('emailProviders')]
    public function test_throws_when_context_has_no_email_template(string $provider): void
    {
        config()->set('raven.default.email', $provider);

        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-updated has no email template id or template file name');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-updated', [
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-updated', config('notification-contexts.user-updated'));

        $scroll = Scroll::make()
            ->for('user-updated')
            ->to($user);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
    }

    /**
     * SES-specific: stored templates do not support attachments.
     *
     * @throws \Throwable
     */
    public function test_throws_when_ses_stored_template_context_has_attachments(): void
    {
        config()->set('raven.default.email', 'ses');

        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Attachments are not supported with SES stored templates');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.welcome', [
            'email_template_id' => 'MyTemplate',
            'channels' => ['email'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('welcome', config('notification-contexts.welcome'));

        $scroll = Scroll::make()
            ->for('welcome')
            ->to($user)
            ->with(['name' => 'John Doe'])
            ->attach('https://example.com/file.pdf');

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
    }
}
