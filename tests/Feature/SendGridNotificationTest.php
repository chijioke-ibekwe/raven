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

class SendGridNotificationTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'sendgrid');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_sent_when_the_raven_listener_receives_an_email_context()
    {

        Notification::fake();

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
            ->cc(['email@raven.com' => 'Jane Doe'])
            ->with([
                'booking_id' => 'JET12345',
            ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotification::class,
            function (EmailNotification $notification) use ($user, $scroll) {
                $mail = $notification->toSendgrid($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === 'user-created' &&
                    $mail->getTemplateId()->getTemplateId() === 'sendgrid-template' &&
                    $mail->getDynamicTemplateDatas() === [
                        'booking_id' => 'JET12345',
                    ] &&
                    $via === ['sendgrid'];

            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_sent_when_the_an_email_address_is_provided_as_part_of_recipients()
    {

        Notification::fake();

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
            ->to([$user, 'jane.doe@raven.com'])
            ->cc(['email@raven.com' => 'Jane Doe'])
            ->with([
                'booking_id' => 'JET12345',
            ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, 'jane.doe@raven.com'))->handle();

        Notification::assertSentTo(
            $user,
            EmailNotification::class,
            function (EmailNotification $notification) use ($user, $scroll) {
                $mail = $notification->toSendgrid($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === 'user-created' &&
                    $mail->getTemplateId()->getTemplateId() === 'sendgrid-template' &&
                    $mail->getDynamicTemplateDatas() === [
                        'booking_id' => 'JET12345',
                    ] &&
                    $via === ['sendgrid'];
            }
        );

        Notification::assertSentOnDemand(EmailNotification::class);

    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_email_notification_context_has_no_email_template()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-updated has no email template id');
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
            ->to($user)
            ->with([
                'user_id' => '345',
                'date_time' => '11-12-2023 10:51',
            ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL, $user))->handle();
    }
}
