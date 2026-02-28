<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Tests\TestCase;
use Illuminate\Support\Facades\File;

class InstallCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        if (File::exists(config_path('raven.php'))) {
            unlink(config_path('raven.php'));
        }

        if (File::exists(config_path('notification-contexts.php'))) {
            unlink(config_path('notification-contexts.php'));
        }
    }

    public function test_that_install_command_publishes_the_config_file_and_contexts_config(): void
    {
        $this->assertFalse(File::exists(config_path('raven.php')));
        $this->assertFalse(File::exists(config_path('notification-contexts.php')));

        $command = $this->artisan('raven:install');

        $command->expectsOutput('Published configuration');

        $command->expectsOutput('Published notification contexts configuration');

        $command->execute();

        $this->assertTrue(File::exists(config_path('raven.php')));
        $this->assertTrue(File::exists(config_path('notification-contexts.php')));
    }

    public function test_that_when_config_file_exists_users_can_choose_not_to_overwrite_it()
    {
        File::put(config_path('notification-contexts.php'), '');
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
        File::put(config_path('notification-contexts.php'), '');
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

    public function test_that_contexts_config_is_not_published_when_it_already_exists()
    {
        File::put(config_path('notification-contexts.php'), '');

        $command = $this->artisan('raven:install');

        $command->execute();

        $command->expectsOutput('Notification contexts configuration already exists');

        $this->assertEquals('', file_get_contents(config_path('notification-contexts.php')));
    }
}
