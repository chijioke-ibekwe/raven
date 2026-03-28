<?php

namespace ChijiokeIbekwe\Raven\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'raven:install';

    protected $description = 'Install Raven';

    public function handle(): void
    {
        $this->info('Installing Raven...');

        $this->handleConfigPublishing();

        $this->handleContextsConfigPublishing();

        $this->info('Installed Raven');
    }

    private function handleConfigPublishing(): void
    {
        $this->info('Publishing configuration...');

        if (! $this->configExists()) {
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

    private function handleContextsConfigPublishing(): void
    {
        $this->info('Publishing notification contexts configuration...');

        if (! $this->contextsConfigExists()) {
            $this->publishContextsConfiguration();
            $this->info('Published notification contexts configuration');
        } else {
            $this->info('Notification contexts configuration already exists');
        }
    }

    private function configExists(): bool
    {
        return File::exists(config_path('raven.php'));
    }

    private function contextsConfigExists(): bool
    {
        return File::exists(config_path('notification-contexts.php'));
    }

    private function shouldOverwriteConfig(): bool
    {
        return $this->confirm(
            'Config file already exists. Do you want to overwrite it?',
            false
        );
    }

    private function publishConfiguration($forcePublish = false): void
    {
        $params = [
            '--provider' => "ChijiokeIbekwe\Raven\RavenServiceProvider",
            '--tag' => 'raven-config',
        ];

        if ($forcePublish === true) {
            $params['--force'] = true;
        }

        $this->call('vendor:publish', $params);
    }

    private function publishContextsConfiguration(): void
    {
        $params = [
            '--provider' => "ChijiokeIbekwe\Raven\RavenServiceProvider",
            '--tag' => 'raven-contexts',
        ];

        $this->call('vendor:publish', $params);
    }
}
