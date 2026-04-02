<?php

namespace ChijiokeIbekwe\Raven\Events;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RavenNotificationFailed
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Scroll $scroll,
        public readonly NotificationContext $context,
        public readonly string $channel,
        public readonly mixed $recipient,
        public readonly Throwable $exception,
    ) {
        //
    }
}
