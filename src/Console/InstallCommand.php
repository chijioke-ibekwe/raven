<?php

namespace ChijiokeIbekwe\Messenger\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'messenger:install';

    protected $description = 'Install Messenger';

    public function handle(): void
    {
        $this->info('Installing Messenger...');

        $this->handleConfigPublishing();

        $this->handleMigrationsPublishing();

        $this->info('Installed Messenger');
    }

    private function handleConfigPublishing(): void
    {
        $this->info('Publishing configuration...');

        if (!$this->configExists()) {
            $this->publishConfiguration();
            $this->info('Published configuration');
        } else {
            if ($this->shouldOverwriteConfig()) {
                $this->info('Overwriting configuration file...');
                $this->publishConfiguration($force = true);
            } else {
                $this->info('Existing configuration was not overwritten');
            }
        }
    }

    private function handleMigrationsPublishing(): void
    {
        $this->info('Publishing migrations...');

        if(!$this->migrationsExist()){
            $this->publishMigrations();
            $this->info('Published migrations');

            if($this->shouldRunMigrations()){
                $this->info('Running migrations...');
                $this->call('migrate');
                $this->info('Migrations ran successfully');
            }
        } else {
            $this->info('Migrations already exist');
        }
    }

    private function configExists(): bool
    {
        return File::exists(config_path('messenger.php'));
    }

    private function migrationsExist(): bool
    {
        return File::exists(database_path('migrations/2023_05_12_142923_create_notification_contexts_table.php'))
            || File::exists(database_path('migrations/2023_05_12_142924_create_notification_channels_table.php'));
    }

    private function shouldOverwriteConfig(): bool
    {
        return $this->confirm(
            'Config file already exists. Do you want to overwrite it?',
            false
        );
    }

    private function shouldRunMigrations(): bool
    {
        return $this->confirm(
            'Do you want to run migrations now?',
            true
        );
    }

    private function publishConfiguration($forcePublish = false): void
    {
        $params = [
            '--provider' => "ChijiokeIbekwe\Messenger\MessengerServiceProvider",
            '--tag' => "messenger-config"
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }

    private function publishMigrations(): void
    {
        $params = [
            '--provider' => "ChijiokeIbekwe\Messenger\MessengerServiceProvider",
            '--tag' => "messenger-migrations"
        ];

        $this->call('vendor:publish', $params);
    }
}
