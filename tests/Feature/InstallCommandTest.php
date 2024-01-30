<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use ChijiokeIbekwe\Raven\Tests\TestCase;

class  InstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function tearDown(): void
    {
        parent::tearDown();

        if(File::exists(config_path('raven.php'))){
            unlink(config_path('raven.php'));
        }

        if(!empty(File::allFiles(database_path('migrations')))){
            File::cleanDirectory(database_path('migrations'));
        }
    }

    function test_that_install_command_publishes_the_config_file_and_migrations(): void
    {
        $this->assertFalse(File::exists(config_path('raven.php')));
        $this->assertEmpty(File::allFiles(database_path('migrations')));

        $command = $this->artisan('raven:install');

        $command->expectsOutput('Published configuration');

        $command->expectsOutput('Published migrations');

        $command->expectsConfirmation(
            'Do you want to run migrations now?',
            'yes'
        );

        $command->execute();

        $command->expectsOutput('Migrations ran successfully');

        $this->assertTrue(File::exists(config_path('raven.php')));
        $this->assertNotEmpty(File::allFiles(database_path('migrations')));
        $this->assertTrue(Schema::hasTable('notification_contexts'));
        $this->assertTrue(Schema::hasTable('notification_channels'));
        $this->assertTrue(Schema::hasTable('notification_channel_notification_context'));
    }


    public function test_that_when_config_file_exists_users_can_choose_not_to_overwrite_it()
    {
        File::put(database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php'), '');
        File::put(config_path('raven.php'), 'return [email => mail@raven.com]');

        $this->assertTrue(File::exists(config_path('raven.php')));

        $command = $this->artisan('raven:install');

        $command->expectsConfirmation(
            'Config file already exists. Do you want to overwrite it?',
            'no'
        );

        $command->execute();

        $command->expectsOutput('Existing configuration was not overwritten');

        $this->assertEquals('return [email => mail@raven.com]',
            file_get_contents(config_path('raven.php')));
    }


    public function test_that_when_config_file_exists_users_can_choose_to_overwrite_it()
    {
        File::put(database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php'), '');
        File::put(config_path('raven.php'), 'return [email => mail@raven.com]');

        $this->assertTrue(File::exists(config_path('raven.php')));

        $command = $this->artisan('raven:install');

        $command->expectsConfirmation(
            'Config file already exists. Do you want to overwrite it?',
            'yes'
        );

        $command->execute();

        $command->expectsOutput('Overwriting configuration file...');

        $this->assertEquals(
            file_get_contents(__DIR__.'/../../config/raven.php'),
            file_get_contents(config_path('raven.php'))
        );
    }

    public function test_that_migrations_are_not_published_when_they_already_exist()
    {
        File::put(database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php'), '');

        $command = $this->artisan('raven:install');

        $command->execute();

        $command->expectsOutput('Migrations already exist');

        $this->assertEquals(count(File::allFiles(database_path('migrations'))), 1);
    }

    public function test_that_user_can_publish_migrations_and_not_run_them()
    {
        $this->assertEmpty(File::allFiles(database_path('migrations')));

        $command = $this->artisan('raven:install');

        $command->expectsConfirmation(
            'Do you want to run migrations now?',
            'no'
        );

        $command->execute();

        $command->expectsOutput('Published migrations');

        $this->assertNotEmpty(File::allFiles(database_path('migrations')));
        $this->assertFalse(Schema::hasTable('notification_contexts'));
        $this->assertFalse(Schema::hasTable('notification_channels'));
        $this->assertFalse(Schema::hasTable('notification_channel_notification_context'));
    }
}