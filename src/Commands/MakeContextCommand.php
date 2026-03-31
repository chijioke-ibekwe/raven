<?php

namespace ChijiokeIbekwe\Raven\Commands;

use Illuminate\Console\Command;

use function Laravel\Prompts\multiselect;

class MakeContextCommand extends Command
{
    protected $signature = 'raven:make-context';

    protected $description = 'Create a new notification context entry';

    public function handle(): int
    {
        $configPath = config_path('notification-contexts.php');

        if (! file_exists($configPath)) {
            $this->error('The notification-contexts.php config file does not exist.');
            $this->line('Run: php artisan vendor:publish --tag=raven-contexts');

            return self::FAILURE;
        }

        $name = $this->askForName();
        $description = $this->ask('Enter an optional description (press Enter to skip)');
        $channels = $this->askForChannels();

        $context = array_filter([
            'description' => $description ?: null,
            'channels' => $channels,
            'active' => $this->confirm('Should this context be active?', true),
            ...$this->askForEmailFields($channels),
            ...$this->askForSmsFields($channels),
            ...$this->askForDatabaseFields($channels),
        ], fn ($value) => ! is_null($value));

        $this->displaySummary($name, $context);

        if (! $this->confirm('Do you want to save this context?')) {
            $this->info('Context creation cancelled.');

            return self::SUCCESS;
        }

        $this->writeToConfig($configPath, $name, $context);
        $this->info("Notification context '{$name}' has been created successfully.");

        return self::SUCCESS;
    }

    private function askForName(): string
    {
        $existingContexts = config('notification-contexts') ?? [];

        while (true) {
            $name = $this->ask('Enter the context name');

            if (empty(trim($name))) {
                $this->error('Context name cannot be empty.');

                continue;
            }

            if (array_key_exists($name, $existingContexts)) {
                $this->error("A context with the name '{$name}' already exists.");

                continue;
            }

            return $name;
        }
    }

    private function askForChannels(): array
    {
        return multiselect(
            label: 'Select channels',
            options: ['EMAIL', 'SMS', 'DATABASE'],
            required: 'You must select at least one channel.',
        );
    }

    private function askForEmailFields(array $channels): array
    {
        if (! in_array('EMAIL', $channels)) {
            return [];
        }

        $provider = config('raven.default.email', 'sendgrid');
        $templateSource = config('raven.providers.ses.template_source', 'sendgrid');

        if ($provider === 'ses' && $templateSource === 'filesystem') {
            return [
                'email_template_filename' => $this->ask('Enter the email template filename (e.g. user-verified.html)'),
                'email_subject' => $this->ask('Enter the email subject (supports {{placeholder}} syntax)'),
            ];
        }

        return [
            'email_template_id' => $this->ask('Enter the SendGrid email template ID'),
        ];
    }

    private function askForSmsFields(array $channels): array
    {
        if (! in_array('SMS', $channels)) {
            return [];
        }

        return [
            'sms_template_filename' => $this->ask('Enter the SMS template filename (e.g. user-verified.txt)'),
        ];
    }

    private function askForDatabaseFields(array $channels): array
    {
        if (! in_array('DATABASE', $channels)) {
            return [];
        }

        return [
            'in_app_template_filename' => $this->ask('Enter the in-app template filename (e.g. user-verified.json)'),
        ];
    }

    private function displaySummary(string $name, array $context): void
    {
        $this->newLine();
        $this->info('Summary:');

        $rows = [['name', $name]];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $value = implode(', ', $value);
            } elseif (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }

            $rows[] = [$key, $value];
        }

        $this->table(['Field', 'Value'], $rows);
        $this->newLine();
    }

    private function writeToConfig(string $configPath, string $name, array $context): void
    {
        $existingContexts = config('notification-contexts') ?? [];
        $existingContexts[$name] = $context;

        file_put_contents($configPath, $this->exportConfig($existingContexts));
    }

    private function exportConfig(array $contexts): string
    {
        $lines = ['<?php', '', 'return ['];

        foreach ($contexts as $name => $config) {
            $lines[] = "    '{$name}' => [";

            foreach ($config as $key => $value) {
                $lines[] = "        '{$key}' => {$this->exportValue($value)},";
            }

            $lines[] = '    ],';
        }

        $lines[] = '];';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function exportValue(mixed $value): string
    {
        if (is_null($value)) {
            return 'null';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            $items = array_map(fn ($v) => "'{$v}'", $value);

            return '['.implode(', ', $items).']';
        }

        return "'".addslashes($value)."'";
    }
}
