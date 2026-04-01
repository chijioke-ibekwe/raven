<?php

namespace ChijiokeIbekwe\Raven\Data;

/**
 * @property string $name
 * @property string|null $description
 * @property string|null $email_template_id
 * @property string|null $email_template_filename
 * @property string|null $email_subject
 * @property string|null $sms_template_filename
 * @property string|null $in_app_template_filename
 * @property bool $active
 * @property array $channels
 * @property array $queue
 * @property bool $encrypted
 */
class NotificationContext
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $description,
        public readonly ?string $email_template_id,
        public readonly ?string $email_template_filename,
        public readonly ?string $email_subject,
        public readonly ?string $sms_template_filename,
        public readonly ?string $in_app_template_filename,
        public readonly bool $active,
        public readonly array $channels,
        public readonly array $queue,
        public readonly bool $encrypted,
    ) {}

    public static function fromConfig(string $name, array $config): self
    {
        return new self(
            name: $name,
            description: $config['description'] ?? null,
            email_template_id: $config['email_template_id'] ?? null,
            email_template_filename: $config['email_template_filename'] ?? null,
            email_subject: $config['email_subject'] ?? null,
            sms_template_filename: $config['sms_template_filename'] ?? null,
            in_app_template_filename: $config['in_app_template_filename'] ?? null,
            active: $config['active'] ?? true,
            channels: $config['channels'] ?? [],
            queue: $config['queue'] ?? [],
            encrypted: $config['encrypted'] ?? false,
        );
    }
}
