<?php

namespace ChijiokeIbekwe\Raven\Tests\Feature;

use ChijiokeIbekwe\Raven\Tests\TestCase;

class MakeContextCommandTest extends TestCase
{
    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configPath = config_path('notification-contexts.php');

        if (! is_dir(dirname($this->configPath))) {
            mkdir(dirname($this->configPath), 0777, true);
        }

        file_put_contents($this->configPath, "<?php\n\nreturn [\n];\n");
    }

    protected function tearDown(): void
    {
        if (file_exists($this->configPath)) {
            unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function getEnvironmentSetUp($app): void
    {
        config()->set('raven.default.email', 'sendgrid');
    }

    public function test_that_context_is_created_with_sendgrid_email_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'order-confirmed')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', 'Order confirmation email')
            ->expectsChoice('Select channels', ['EMAIL'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-abc123')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'order-confirmed'],
                ['description', 'Order confirmation email'],
                ['channels', 'EMAIL'],
                ['active', 'true'],
                ['email_template_id', 'd-abc123'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->expectsOutput("Notification context 'order-confirmed' has been created successfully.")
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('order-confirmed', $config);
        $this->assertEquals('Order confirmation email', $config['order-confirmed']['description']);
        $this->assertEquals(['EMAIL'], $config['order-confirmed']['channels']);
        $this->assertTrue($config['order-confirmed']['active']);
        $this->assertEquals('d-abc123', $config['order-confirmed']['email_template_id']);
    }

    public function test_that_context_is_created_with_ses_filesystem_email_channel(): void
    {
        config()->set('raven.default.email', 'ses');
        config()->set('raven.providers.ses.template_source', 'filesystem');

        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'welcome-email')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['EMAIL'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the email template filename (e.g. user-verified.html)', 'welcome.html')
            ->expectsQuestion('Enter the email subject (supports {{placeholder}} syntax)', 'Welcome, {{name}}!')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'welcome-email'],
                ['channels', 'EMAIL'],
                ['active', 'true'],
                ['email_template_filename', 'welcome.html'],
                ['email_subject', 'Welcome, {{name}}!'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('welcome-email', $config);
        $this->assertEquals('welcome.html', $config['welcome-email']['email_template_filename']);
        $this->assertEquals('Welcome, {{name}}!', $config['welcome-email']['email_subject']);
        $this->assertArrayNotHasKey('description', $config['welcome-email']);
    }

    public function test_that_context_is_created_with_sms_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'otp-sent')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['SMS'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'otp.txt')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'otp-sent'],
                ['channels', 'SMS'],
                ['active', 'true'],
                ['sms_template_filename', 'otp.txt'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('otp-sent', $config);
        $this->assertEquals('otp.txt', $config['otp-sent']['sms_template_filename']);
    }

    public function test_that_context_is_created_with_database_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'task-assigned')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['DATABASE'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the in-app template filename (e.g. user-verified.json)', 'task-assigned.json')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'task-assigned'],
                ['channels', 'DATABASE'],
                ['active', 'true'],
                ['in_app_template_filename', 'task-assigned.json'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('task-assigned', $config);
        $this->assertEquals('task-assigned.json', $config['task-assigned']['in_app_template_filename']);
    }

    public function test_that_context_is_created_with_multiple_channels(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'user-verified')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', 'User verification notification')
            ->expectsChoice('Select channels', ['EMAIL', 'SMS', 'DATABASE'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-xyz789')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'user-verified.txt')
            ->expectsQuestion('Enter the in-app template filename (e.g. user-verified.json)', 'user-verified.json')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'user-verified'],
                ['description', 'User verification notification'],
                ['channels', 'EMAIL, SMS, DATABASE'],
                ['active', 'true'],
                ['email_template_id', 'd-xyz789'],
                ['sms_template_filename', 'user-verified.txt'],
                ['in_app_template_filename', 'user-verified.json'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('user-verified', $config);
        $this->assertEquals(['EMAIL', 'SMS', 'DATABASE'], $config['user-verified']['channels']);
        $this->assertEquals('d-xyz789', $config['user-verified']['email_template_id']);
        $this->assertEquals('user-verified.txt', $config['user-verified']['sms_template_filename']);
        $this->assertEquals('user-verified.json', $config['user-verified']['in_app_template_filename']);
    }

    public function test_that_command_aborts_on_declined_confirmation(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'temp-context')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['EMAIL'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-temp')
            ->expectsConfirmation('Do you want to save this context?', 'no')
            ->expectsOutput('Context creation cancelled.')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayNotHasKey('temp-context', $config);
    }

    public function test_that_command_fails_when_config_file_does_not_exist(): void
    {
        unlink($this->configPath);

        $this->artisan('raven:make-context')
            ->expectsOutput('The notification-contexts.php config file does not exist.')
            ->assertFailed();
    }

    public function test_that_new_context_is_appended_to_existing_contexts(): void
    {
        $existing = <<<'PHP'
<?php

return [
    'existing-context' => [
        'channels' => ['EMAIL'],
        'active' => true,
        'email_template_id' => 'd-existing',
    ],
];
PHP;

        file_put_contents($this->configPath, $existing);
        config()->set('notification-contexts.existing-context', [
            'channels' => ['EMAIL'],
            'active' => true,
            'email_template_id' => 'd-existing',
        ]);

        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'new-context')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['SMS'], ['EMAIL', 'SMS', 'DATABASE'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'new.txt')
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('existing-context', $config);
        $this->assertArrayHasKey('new-context', $config);
        $this->assertEquals('d-existing', $config['existing-context']['email_template_id']);
        $this->assertEquals('new.txt', $config['new-context']['sms_template_filename']);
    }
}
