<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Tests\TestCase;
use ChijiokeIbekwe\Raven\Tests\Utilities\User;

class NotificationContextRouteTest extends TestCase
{
    use RefreshDatabase;

    public function getEnvironmentSetUp($app): void
    {
        $migrations = require __DIR__.'/../../database/migrations/create_notification_contexts_table.php.stub';

        $migrations->up();
    }

    public function test_that_authorized_users_can_successfully_fetch_all_notification_contexts ()
    {
        $user = User::factory(1)->make([
            'name' => 'John Doe',
            'email' => 'john.doe@raven.com'
        ])->get(0);

        NotificationContext::factory(1)->create([
            'name' => 'user-verified',
            'in_app_template_filename' => 'verification.json',
            'type' => 'user',
            'channels' => ['DATABASE']
        ])->get(0);

        $response = $this->actingAs($user)->getJson(route('contexts.index'));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'message' => 'Notification contexts retrieved successfully',
            'data' => [
                [
                    'id' => 1,
                    'email_template_id' => null,
                    'email_template_filename' => null,
                    'name' => 'user-verified',
                    'in_app_template_filename' => 'verification.json',
                    'sms_template_filename' => null,
                    'type' => 'user',
                    'channels' => ['DATABASE']
                ]
            ]
        ]);
    }
}