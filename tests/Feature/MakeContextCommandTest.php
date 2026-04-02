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
        config()->set('raven.customizations.templates_directory', resource_path('templates'));
    }

    public function test_that_context_is_created_with_sendgrid_email_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'order-confirmed')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', 'Order confirmation email')
            ->expectsChoice('Select channels', ['email'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-abc123')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'order-confirmed'],
                ['description', 'Order confirmation email'],
                ['channels', 'email'],
                ['active', 'true'],
                ['email_template_id', 'd-abc123'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->expectsOutput("Notification context 'order-confirmed' has been created successfully.")
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('order-confirmed', $config);
        $this->assertEquals('Order confirmation email', $config['order-confirmed']['description']);
        $this->assertEquals(['email'], $config['order-confirmed']['channels']);
        $this->assertTrue($config['order-confirmed']['active']);
        $this->assertEquals('d-abc123', $config['order-confirmed']['email_template_id']);
    }

    public function test_that_context_is_created_with_ses_email_channel(): void
    {
        config()->set('raven.default.email', 'ses');

        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'welcome-email')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['email'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the email template filename (e.g. user-verified.html)', 'welcome.html')
            ->expectsQuestion('Enter the email subject (supports {{placeholder}} syntax)', 'Welcome, {{name}}!')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'welcome-email'],
                ['channels', 'email'],
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
        $this->assertFileExists(resource_path('templates/email/welcome.html'));
    }

    public function test_that_context_is_created_with_sms_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'otp-sent')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['sms'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'otp.txt')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'otp-sent'],
                ['channels', 'sms'],
                ['active', 'true'],
                ['sms_template_filename', 'otp.txt'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('otp-sent', $config);
        $this->assertEquals('otp.txt', $config['otp-sent']['sms_template_filename']);
        $this->assertFileExists(resource_path('templates/sms/otp.txt'));
    }

    public function test_that_context_is_created_with_database_channel(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'task-assigned')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['database'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the in-app template filename (e.g. user-verified.json)', 'task-assigned.json')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'task-assigned'],
                ['channels', 'database'],
                ['active', 'true'],
                ['in_app_template_filename', 'task-assigned.json'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('task-assigned', $config);
        $this->assertEquals('task-assigned.json', $config['task-assigned']['in_app_template_filename']);
        $this->assertFileExists(resource_path('templates/in_app/task-assigned.json'));
    }

    public function test_that_context_is_created_with_multiple_channels(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'user-verified')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', 'User verification notification')
            ->expectsChoice('Select channels', ['email', 'sms', 'database'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-xyz789')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'user-verified.txt')
            ->expectsQuestion('Enter the in-app template filename (e.g. user-verified.json)', 'user-verified.json')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'user-verified'],
                ['description', 'User verification notification'],
                ['channels', 'email, sms, database'],
                ['active', 'true'],
                ['email_template_id', 'd-xyz789'],
                ['sms_template_filename', 'user-verified.txt'],
                ['in_app_template_filename', 'user-verified.json'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('user-verified', $config);
        $this->assertEquals(['email', 'sms', 'database'], $config['user-verified']['channels']);
        $this->assertEquals('d-xyz789', $config['user-verified']['email_template_id']);
        $this->assertEquals('user-verified.txt', $config['user-verified']['sms_template_filename']);
        $this->assertEquals('user-verified.json', $config['user-verified']['in_app_template_filename']);
        $this->assertFileExists(resource_path('templates/sms/user-verified.txt'));
        $this->assertFileExists(resource_path('templates/in_app/user-verified.json'));
    }

    public function test_that_existing_template_files_are_not_overwritten(): void
    {
        $smsDir = resource_path('templates/sms');
        if (! is_dir($smsDir)) {
            mkdir($smsDir, 0755, true);
        }
        file_put_contents($smsDir.'/existing.txt', 'Hello {{name}}');

        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'existing-template')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['sms'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'existing.txt')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $this->assertEquals('Hello {{name}}', file_get_contents($smsDir.'/existing.txt'));
    }

    public function test_that_command_aborts_on_declined_confirmation(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'temp-context')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['email'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-temp')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
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
        'channels' => ['email'],
        'active' => true,
        'email_template_id' => 'd-existing',
    ],
];
PHP;

        file_put_contents($this->configPath, $existing);
        config()->set('notification-contexts.existing-context', [
            'channels' => ['email'],
            'active' => true,
            'email_template_id' => 'd-existing',
        ]);

        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'new-context')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['sms'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'new.txt')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('existing-context', $config);
        $this->assertArrayHasKey('new-context', $config);
        $this->assertEquals('d-existing', $config['existing-context']['email_template_id']);
        $this->assertEquals('new.txt', $config['new-context']['sms_template_filename']);
    }

    public function test_that_context_is_created_with_encrypted_flag(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'password-reset')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['email'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-reset123')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'yes')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'no')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'password-reset'],
                ['channels', 'email'],
                ['active', 'true'],
                ['email_template_id', 'd-reset123'],
                ['encrypted', 'true'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('password-reset', $config);
        $this->assertTrue($config['password-reset']['encrypted']);
    }

    public function test_that_context_is_created_with_per_channel_queue_routing(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'order-shipped')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['email', 'sms'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-ship456')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'order-shipped.txt')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'yes')
            ->expectsQuestion("Enter the queue name for 'email' (press Enter to skip)", 'notifications')
            ->expectsQuestion("Enter the queue connection for 'email' (press Enter to skip)", 'redis')
            ->expectsQuestion("Enter the queue name for 'sms' (press Enter to skip)", 'critical')
            ->expectsQuestion("Enter the queue connection for 'sms' (press Enter to skip)", 'sqs')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'order-shipped'],
                ['channels', 'email, sms'],
                ['active', 'true'],
                ['email_template_id', 'd-ship456'],
                ['sms_template_filename', 'order-shipped.txt'],
                ['queue.email', 'queue: notifications, connection: redis'],
                ['queue.sms', 'queue: critical, connection: sqs'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('order-shipped', $config);
        $this->assertEquals('notifications', $config['order-shipped']['queue']['email']['queue']);
        $this->assertEquals('redis', $config['order-shipped']['queue']['email']['connection']);
        $this->assertEquals('critical', $config['order-shipped']['queue']['sms']['queue']);
        $this->assertEquals('sqs', $config['order-shipped']['queue']['sms']['connection']);
    }

    public function test_that_context_queue_routing_skips_channels_with_no_config(): void
    {
        $this->artisan('raven:make-context')
            ->expectsQuestion('Enter the context name', 'mixed-queue')
            ->expectsQuestion('Enter an optional description (press Enter to skip)', '')
            ->expectsChoice('Select channels', ['email', 'sms'], ['email', 'sms', 'database'])
            ->expectsConfirmation('Should this context be active?', 'yes')
            ->expectsQuestion('Enter the SendGrid email template ID', 'd-mixed')
            ->expectsQuestion('Enter the SMS template filename (e.g. user-verified.txt)', 'mixed.txt')
            ->expectsConfirmation('Should queue payloads be encrypted?', 'no')
            ->expectsConfirmation('Do you want to configure per-channel queue routing?', 'yes')
            ->expectsQuestion("Enter the queue name for 'email' (press Enter to skip)", 'high-priority')
            ->expectsQuestion("Enter the queue connection for 'email' (press Enter to skip)", '')
            ->expectsQuestion("Enter the queue name for 'sms' (press Enter to skip)", '')
            ->expectsQuestion("Enter the queue connection for 'sms' (press Enter to skip)", '')
            ->expectsTable(['Field', 'Value'], [
                ['name', 'mixed-queue'],
                ['channels', 'email, sms'],
                ['active', 'true'],
                ['email_template_id', 'd-mixed'],
                ['sms_template_filename', 'mixed.txt'],
                ['queue.email', 'queue: high-priority'],
            ])
            ->expectsConfirmation('Do you want to save this context?', 'yes')
            ->assertSuccessful();

        $config = include $this->configPath;

        $this->assertArrayHasKey('mixed-queue', $config);
        $this->assertEquals('high-priority', $config['mixed-queue']['queue']['email']['queue']);
        $this->assertArrayNotHasKey('sms', $config['mixed-queue']['queue']);
    }
}
