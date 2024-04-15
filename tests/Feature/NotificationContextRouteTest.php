<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ChijiokeIbekwe\Raven\Models\NotificationChannel;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;

class NotificationContextRouteTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        // run the up() method (perform the migration)
        (new \CreateNotificationContextsTable)->up();
        (new \CreateNotificationChannelsTable)->up();
        (new \CreateNotificationChannelNotificationContextTable)->up();
    }

    public function test_that_authorized_users_can_successfully_fetch_all_notification_contexts ()
    {

        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ])->get(0);

        $context = NotificationContext::factory(1)->create([
            'name' => 'user-verified',
            'title' => 'Verification',
            'body' => 'User with id {user_id} has been verified on the platform on {date_time}',
            'type' => 'user'
        ])->get(0);

        $channel = NotificationChannel::where('type', 'DATABASE')->first();

        $context->notification_channels()->attach($channel->id);

        $response = $this->actingAs($user)->getJson(route('contexts.index'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => true,
            'msg' => 'Success',
            'data' => [
                [
                    'id' => 1,
                    'email_template' => null,
                    'name' => 'user-verified',
                    'title' => 'Verification',
                    'body' => 'User with id {user_id} has been verified on the platform on {date_time}',
                    'type' => 'user',
                    'notification_channels' => [
                        [
                            'id' => 2,
                            'type' => 'DATABASE'
                        ]
                    ]
                ]
            ]
        ]);
    }
}