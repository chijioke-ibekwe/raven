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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setCcs(['email@raven.com' => 'Jane Doe']);
        $scroll->setParams([
            'booking_id' => 'JET12345',
        ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();

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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user, 'jane.doe@raven.com']);
        $scroll->setCcs(['email@raven.com' => 'Jane Doe']);
        $scroll->setParams([
            'booking_id' => 'JET12345',
        ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();

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
            'channels' => ['EMAIL'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-updated', config('notification-contexts.user-updated'));

        $scroll = new Scroll;
        $scroll->setContextName('user-updated');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51',
        ]);

        (new RavenChannelJob($scroll, $context, ChannelType::EMAIL))->handle();
    }
}
