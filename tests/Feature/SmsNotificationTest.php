<?php

use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Listeners\RavenListener;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

class SmsNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.sms', 'vonage');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
        config()->set('raven.customizations.sms.from.name', 'Raven');

        $migrations = require __DIR__.'/../../database/migrations/create_notification_contexts_table.php.stub';

        $migrations->up();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_sms_notifications_are_sent_when_the_raven_listener_receives_an_sms_context(){

        $text = 'User with name {{name}}, has been created on the platform.';

        if (!is_dir(resource_path('templates/sms'))) {
            mkdir(resource_path('templates/sms'), 0777, true);
        }

        file_put_contents(resource_path('templates/sms/user-created.txt'), $text);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
            'phone_number' => '+2349011112222'
        ]);

        $context = NotificationContext::factory()->create([
            'sms_template_filename' => 'user-created.txt',
            'name' => 'user-created',
            'channels' => ['SMS']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'name' => $user->name
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );

        Notification::assertSentTo(
            $user,
            SmsNotificationSender::class,
            function (SmsNotificationSender $notification) use ($user, $scroll, $context) {
                $sms = $notification->toVonage($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === $context->name &&
                    $sms->getMessage() === 'User with name John Doe, has been created on the platform.' &&
                    $sms->getTo() === '+2349011112222' &&
                    $via === ['vonage'];
            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_sms_notifications_are_sent_when_a_phone_number_is_provided_as_part_of_the_recipients(){

        $text = 'User with name {{name}}, has been created on the platform.';

        if (!is_dir(resource_path('templates/sms'))) {
            mkdir(resource_path('templates/sms'), 0777, true);
        }

        file_put_contents(resource_path('templates/sms/user-created.txt'), $text);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
            'phone_number' => '+2349011112222'
        ]);

        $context = NotificationContext::factory()->create([
            'sms_template_filename' => 'user-created.txt',
            'name' => 'user-created',
            'channels' => ['SMS']
        ]);

        $scroll = new Scroll();
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user, '+2347092223333']);
        $scroll->setParams([
            'name' => $user->name
        ]);

        (new RavenListener())->handle(
            new Raven($scroll)
        );

        Notification::assertSentOnDemand(
            SmsNotificationSender::class,
            function (SmsNotificationSender $notification) use ($scroll, $context) {

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === $context->name;
            }
        );

        Notification::assertTimesSent(2, SmsNotificationSender::class);
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_sms_notification_context_has_no_filename()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('SMS notification context with name user-updated has no template filename');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
            'phone_number' => '+2349011112222'
        ]);

        NotificationContext::factory()->create([
            'name' => 'user-updated',
            'channels' => ['SMS']
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
    public function test_that_exception_is_thrown_when_sms_notification_context_template_file_does_not_exist()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        NotificationContext::factory()->create([
            'name' => 'user-updated',
            'sms_template_filename' => 'user-updated.txt',
            'channels' => ['SMS']
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
    public function test_that_exception_is_thrown_when_a_non_notifiable_recipient_is_provided_in_notification_data()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Notification recipient is not a notifiable');
        $this->expectExceptionCode(422);

        Notification::fake();

        $context = NotificationContext::factory()->create([
            'email_template_id' => 'sendgrid-template',
            'name' => 'user-created',
            'channels' => ['SMS']
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
}
