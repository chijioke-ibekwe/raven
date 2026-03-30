<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Jobs\Raven;
use ChijiokeIbekwe\Raven\Notifications\DatabaseNotification;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;
use Illuminate\Support\Facades\Notification;

class DatabaseNotificationTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    /**
     * @throws \Throwable
     */
    public function test_that_database_notifications_are_sent_when_the_raven_listener_receives_a_database_context()
    {
        $data = [
            'title' => 'Verification',
            'body' => 'User with id {{user_id}} has been verified on the platform on {{date_time}}',
        ];

        $json = json_encode($data, JSON_PRETTY_PRINT);

        if (! is_dir(resource_path('templates/in_app'))) {
            mkdir(resource_path('templates/in_app'), 0777, true);
        }

        file_put_contents(resource_path('templates/in_app/user-verified.json'), $json);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-verified', [
            'in_app_template_filename' => 'user-verified.json',
            'type' => 'user',
            'channels' => ['DATABASE'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-verified');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51',
        ]);

        (new Raven($scroll))->handle();

        Notification::assertSentTo(
            $user,
            DatabaseNotification::class,
            function (DatabaseNotification $notification) use ($user, $scroll) {
                $content = $notification->toDatabase($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === 'user-verified' &&
                    data_get($content, 'title') === 'Verification' &&
                    data_get($content, 'body') === 'User with id 345 has been verified on the platform on 11-12-2023 10:51' &&
                    $via === ['database'];
            }
        );
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_database_notification_context_has_no_filename()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionMessage('Database notification context with name user-updated has no template filename');
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-updated', [
            'channels' => ['DATABASE'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-updated');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51',
        ]);

        (new Raven($scroll))->handle();
    }

    /**
     * @throws \Throwable
     */
    public function test_that_exception_is_thrown_when_database_notification_context_template_file_does_not_exist()
    {
        $this->expectException(RavenInvalidDataException::class);
        $this->expectExceptionCode(422);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com',
        ]);

        config()->set('notification-contexts.user-updated', [
            'in_app_template_filename' => 'user-updated.json',
            'channels' => ['DATABASE'],
            'active' => true,
        ]);

        $scroll = new Scroll;
        $scroll->setContextName('user-updated');
        $scroll->setRecipients($user);
        $scroll->setParams([
            'user_id' => '345',
            'date_time' => '11-12-2023 10:51',
        ]);

        (new Raven($scroll))->handle();
    }
}
