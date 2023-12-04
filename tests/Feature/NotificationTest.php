<?php

namespace ChijiokeIbekwe\Messenger\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use ChijiokeIbekwe\Messenger\Data\NotificationData;
use ChijiokeIbekwe\Messenger\Events\MessengerEvent;
use ChijiokeIbekwe\Messenger\Exceptions\MessengerEntityNotFoundException;
use ChijiokeIbekwe\Messenger\Exceptions\MessengerInvalidDataException;
use ChijiokeIbekwe\Messenger\Listeners\MessengerListener;
use ChijiokeIbekwe\Messenger\Models\NotificationChannel;
use ChijiokeIbekwe\Messenger\Models\NotificationContext;
use ChijiokeIbekwe\Messenger\Notifications\DatabaseNotificationSender;
use ChijiokeIbekwe\Messenger\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Messenger\Tests\TestCase;
use ChijiokeIbekwe\Messenger\Tests\Utilities\User;
use Illuminate\Http\UploadedFile;

class  NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('messenger.notification-service.email', 'sendgrid-mail');
        $app['config']->set('messenger.notification-service.database', 'database');
        $app['config']->set('database.default', 'test-db');
        $app['config']->set('database.connections.test-db', [
            'driver' => 'sqlite',
            'database' => ':memory:'
        ]);

        // run the up() method (perform the migration)
        (new \CreateNotificationContextsTable)->up();
        (new \CreateNotificationChannelsTable)->up();
        (new \CreateNotificationChannelNotificationContextTable)->up();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_email_notifications_are_sent_when_the_messenger_listener_receives_an_email_context(){

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'EMAIL')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-created');
        $data->setRecipients($user);
        $data->setCcs(["email@messenger.com" => "Jane Doe"]);
        $data->setParams([
            'booking_id' => 'JET12345'
        ]);
        $data->setAttachments('https://cdn.iconscout.com/icon/free/png-256/free-docker-226091.png');

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );

        Notification::assertCount(1);

        Notification::assertSentTo(
            $user,
            EmailNotificationSender::class,
            function (EmailNotificationSender $notification) use ($user, $data, $context) {
                $mail = $notification->toSendgrid($user);
                $via = $notification->via($user);

                return $notification->notificationData === $data &&
                    $notification->notificationContext->name === $context->name &&
                    $mail->getTemplateId()->getTemplateId() === 'sendgrid-template' &&
                    $mail->getDynamicTemplateDatas() === [
                        'booking_id' => 'JET12345'
                    ] &&
                    $via === ['sendgrid-mail'];

            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_database_notifications_are_sent_when_the_messenger_listener_receives_a_database_context()
    {

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'name' => 'user-verified',
            'title' => 'Verification',
            'body' => 'User with id {user_id} has been verified on the platform on {date_time}',
            'type' => 'user'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'DATABASE')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-verified');
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );

        Notification::assertCount(1);

        Notification::assertSentTo(
            $user,
            DatabaseNotificationSender::class,
            function (DatabaseNotificationSender $notification) use ($user, $data, $context) {
                $content = $notification->toDatabase($user);
                $via = $notification->via($user);

                return $notification->notificationData === $data &&
                    $notification->notificationContext->name === $context->name &&
                    data_get($content, 'title') === 'Verification' &&
                    data_get($content, 'body') === 'User with id 345 has been verified on the platform on 11-12-2023 10:51' &&
                    data_get($content, 'type') === 'user' &&
                    data_get($content, 'id') === 345 &&
                    $via === ['database'];
            }
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_notification_context_name_is_not_provided_in_data()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Notification context name is not set');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $data = new NotificationData();
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    public function test_that_exception_is_thrown_when_notification_context_name_does_not_exist_on_the_database()
    {
        $this->expectException(MessengerEntityNotFoundException::class);
        $this->expectExceptionMessage('Notification context with name user-verified does not exist');
        $this->expectExceptionCode(404);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $data = new NotificationData();
        $data->setContextName('user-verified');
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    public function test_that_exception_is_thrown_when_email_notification_context_has_no_email_template_id()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Email notification context with name user-updated has no email template id');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'name' => 'user-updated'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'EMAIL')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-updated');
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    public function test_that_exception_is_thrown_when_database_notification_context_has_no_title()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Database notification context with name user-updated has no title');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'name' => 'user-updated',
            'body' => 'User with id {user_id} has been updated on {date_time}'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'DATABASE')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-updated');
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    public function test_that_exception_is_thrown_when_database_notification_context_has_no_body()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Database notification context with name user-updated has no body');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'name' => 'user-updated',
            'title' => 'User Updated'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'DATABASE')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-updated');
        $data->setRecipients($user);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    public function test_that_exception_is_thrown_when_recipients_are_not_provided_in_notification_data()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not set');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@messenger.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'EMAIL')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-created');
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_a_non_notifiable_recipient_is_provided_in_notification_data()
    {
        $this->expectException(MessengerInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not notifiable');
        $this->expectExceptionCode(422);

        Notification::fake();

        $context = NotificationContext::factory(1)->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'EMAIL')->first();

        $context->notification_channels()->attach($channel->id);

        $data = new NotificationData();
        $data->setContextName('user-created');
        $data->setRecipients($channel);
        $data->setParams([
            'id' => 345,
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51'
        ]);

        (new MessengerListener())->handle(
            new MessengerEvent($data)
        );
    }
}
