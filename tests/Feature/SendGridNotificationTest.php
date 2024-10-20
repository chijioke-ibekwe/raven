<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Exceptions\RavenEntityNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Listeners\RavenListener;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;

class SendGridNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'sendgrid');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));

        $migrations = require __DIR__.'/../../database/migrations/create_notification_contexts_table.php.stub';

        $migrations->up();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_sent_when_the_raven_listener_receives_an_email_context(){

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        $context = NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['EMAIL']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setCcs(["email@raven.com" => "Jane Doe"]);
        $scroll->setParams([
            'booking_id' => 'JET12345'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );

        Notification::assertSentTo(
            $user,
            EmailNotificationSender::class,
            function (EmailNotificationSender $notification) use ($user, $scroll, $context) {
                $mail = $notification->toSendgrid($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === $context->name &&
                    $mail->getTemplateId()->getTemplateId() === 'sendgrid-template' &&
                    $mail->getDynamicTemplateDatas() === [
                        'booking_id' => 'JET12345'
                    ] &&
                    $via === ['sendgrid'];

            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_sent_when_the_an_email_address_is_provided_as_part_of_recipients(){

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        $context = NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['EMAIL']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user, 'jane.doe@raven.com']);
        $scroll->setCcs(["email@raven.com" => "Jane Doe"]);
        $scroll->setParams([
            'booking_id' => 'JET12345'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );

        Notification::assertSentTo(
            $user,
            EmailNotificationSender::class,
            function (EmailNotificationSender $notification) use ($user, $scroll, $context) {
                $mail = $notification->toSendgrid($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === $context->name &&
                    $mail->getTemplateId()->getTemplateId() === 'sendgrid-template' &&
                    $mail->getDynamicTemplateDatas() === [
                        'booking_id' => 'JET12345'
                    ] &&
                    $via === ['sendgrid'];
            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_notification_context_name_is_not_provided_in_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification context name is not set');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        $scroll = new Scroll();
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_notification_context_name_does_not_exist_on_the_database()
    {
        $this->expectException(RavenEntityNotFoundException::class);
        $this->expectExceptionMessage('Notification context with name user-verified does not exist');
        $this->expectExceptionCode(404);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-verified');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
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
            'email' => 'john.doe@raven.com'
        ]);

        NotificationContext::factory()->create([
            'name' => 'user-updated',
            'channels' => ['EMAIL']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-updated');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_recipients_are_not_provided_in_notification_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not set');
        $this->expectExceptionCode(422);

        Notification::fake();

        User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['EMAIL']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_a_non_notifiable_recipient_is_provided_in_notification_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not a notifiable');
        $this->expectExceptionCode(422);

        Notification::fake();

        $context = NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['EMAIL']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients($context);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_a_notification_context_has_an_invalid_channel()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification context has an invalid channel: em');

        Notification::fake();

        NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['em']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients('john.doe@raven.com');
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );
    }
}
