<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\RavenChannelJob;
use ChijiokeIbekwe\Raven\Notifications\SmsNotification;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Notification;

class SmsNotificationTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.sms', 'vonage');
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
        config()->set('raven.customizations.sms.from.name', 'Raven');
    }

    /**
     * @throws \Throwable
     */
    public function test_that_sms_notifications_are_sent_when_the_raven_listener_receives_an_sms_context()
    {

        $text = 'User with name {{name}}, has been created on the platform.';

        if (! is_dir(resource_path('templates/sms'))) {
            mkdir(resource_path('templates/sms'), 0777, true);
        }

        file_put_contents(resource_path('templates/sms/user-created.txt'), $text);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
            'phone_number' => '+2349011112222',
        ]);

        config()->set('notification-contexts.user-created', [
            'sms_template_filename' => 'user-created.txt',
            'channels' => ['SMS'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'name' => $user->name,
        ]);

        (new RavenChannelJob($scroll, $context, ChannelType::SMS))->handle();

        Notification::assertSentTo(
            $user,
            SmsNotification::class,
            function (SmsNotification $notification) use ($user, $scroll) {
                $sms = $notification->toVonage($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === 'user-created' &&
                    $sms->getMessage() === 'User with name John Doe, has been created on the platform.' &&
                    $sms->getTo() === '+2349011112222' &&
                    $via === ['vonage'];
            }
        );

    }

    /**
     * @throws \Throwable
     */
    public function test_that_sms_notifications_are_sent_when_a_phone_number_is_provided_as_part_of_the_recipients()
    {

        $text = 'User with name {{name}}, has been created on the platform.';

        if (! is_dir(resource_path('templates/sms'))) {
            mkdir(resource_path('templates/sms'), 0777, true);
        }

        file_put_contents(resource_path('templates/sms/user-created.txt'), $text);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
            'phone_number' => '+2349011112222',
        ]);

        config()->set('notification-contexts.user-created', [
            'sms_template_filename' => 'user-created.txt',
            'channels' => ['SMS'],
            'active' => true,
        ]);

        $context = NotificationContext::fromConfig('user-created', config('notification-contexts.user-created'));

        $scroll = new Scroll;
        $scroll->setContextName('user-created');
        $scroll->setRecipients([$user, '+2347092223333']);
        $scroll->setParams([
            'name' => $user->name,
        ]);

        (new RavenChannelJob($scroll, $context, ChannelType::SMS))->handle();

        Notification::assertSentOnDemand(
            SmsNotification::class,
            function (SmsNotification $notification) use ($scroll) {

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === 'user-created';
            }
        );

        Notification::assertSentTimes(SmsNotification::class, 2);
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
            'phone_number' => '+2349011112222',
        ]);

        config()->set('notification-contexts.user-updated', [
            'channels' => ['SMS'],
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

        (new RavenChannelJob($scroll, $context, ChannelType::SMS))->handle();
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
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-updated', [
            'sms_template_filename' => 'user-updated.txt',
            'channels' => ['SMS'],
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

        (new RavenChannelJob($scroll, $context, ChannelType::SMS))->handle();
    }
}
