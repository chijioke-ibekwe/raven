<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Listeners\RavenListener;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Notifications\DatabaseNotificationSender;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;

class  DatabaseNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.customizations.templates_directory', resource_path('templates'));

        $migrations = require __DIR__.'/../../database/migrations/create_notification_contexts_table.php.stub';

        $migrations->up();
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

        if (!is_dir(resource_path('templates/in_app'))) {
            mkdir(resource_path('templates/in_app'), 0777, true);
        }

        file_put_contents(resource_path('templates/in_app/user-verified.json'), $json);

        Notification::fake();

        $user = User::factory()->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ]);

        $context = NotificationContext::factory()->create([
            'name' => 'user-verified',
            'in_app_template_filename' => 'user-verified.json',
            'type' => 'user',
            'channels' => ['DATABASE']
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

        Notification::assertSentTo(
            $user,
            DatabaseNotificationSender::class,
            function (DatabaseNotificationSender $notification) use ($user, $scroll, $context) {
                $content = $notification->toDatabase($user);
                $via = $notification->via($user);

                return $notification->scroll === $scroll &&
                    $notification->notificationContext->name === $context->name &&
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
            'email' => 'john.doe@raven.com'
        ]);

        NotificationContext::factory()->create([
            'name' => 'user-updated',
            'channels' => ['DATABASE']
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
    public function test_that_exception_is_thrown_when_database_notification_context_template_file_does_not_exist()
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
            'in_app_template_filename' => 'user-updated.json',
            'channels' => ['DATABASE']
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
}
