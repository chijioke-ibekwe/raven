<?php

namespace ChijiokeIbekwe\Raven\Enums;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Notifications\DatabaseNotification;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Notifications\RavenNotification;
use ChijiokeIbekwe\Raven\Notifications\SmsNotification;

enum ChannelType: string
{
    case EMAIL = 'EMAIL';
    case SMS = 'SMS';
    case DATABASE = 'DATABASE';

    public function createNotification(Scroll $scroll, NotificationContext $context): RavenNotification
    {
        return match ($this) {
            self::EMAIL => new EmailNotification($scroll, $context),
            self::SMS => new SmsNotification($scroll, $context),
            self::DATABASE => new DatabaseNotification($scroll, $context),
        };
    }
}
