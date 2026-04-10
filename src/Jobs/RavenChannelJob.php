<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Notifications\SmsNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * @internal This job is dispatched by the Raven job. Do not dispatch directly.
 */
class RavenChannelJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const EMAIL_PATTERN = '#^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$#';

    const PHONE_PATTERN = '#^\+?[0-9\s\-()]+$#';

    public function __construct(
        public Scroll $scroll,
        public NotificationContext $context,
        public ChannelType $channelType,
        public mixed $recipient,
    ) {
        $channelKey = strtolower($this->channelType->name);
        $channelQueue = $context->queue[$channelKey] ?? [];

        $queue = $channelQueue['queue']
            ?? config('raven.customizations.queue_name');

        $connection = $channelQueue['connection']
            ?? config('raven.customizations.queue_connection');

        if ($queue) {
            $this->onQueue($queue);
        }

        if ($connection) {
            $this->onConnection($connection);
        }
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        Log::info("Processing notification for context {$this->context->name} through channel {$this->channelType->name}");

        $notification = $this->channelType->createNotification($this->scroll, $this->context);
        $notification->validateNotification();

        Log::info("Sending notification for context {$this->context->name} through channel {$this->channelType->name}");

        try {
            $sent = $this->sendToRecipient($notification);

            if ($sent) {
                RavenNotificationSent::dispatch($this->scroll, $this->context, $this->channelType->name, $this->recipient);
            }
        } catch (Throwable $e) {
            RavenNotificationFailed::dispatch($this->scroll, $this->context, $this->channelType->name, $this->recipient, $e);
            throw $e;
        }
    }

    private function sendToRecipient(mixed $notification): bool
    {
        if (! is_string($this->recipient)) {
            Notification::send([$this->recipient], $notification);

            return true;
        }

        return $this->resolveRouteWithNotification($this->recipient, $notification);
    }

    private function resolveRouteWithNotification(string $recipient, mixed $notification): bool
    {
        if ($notification instanceof EmailNotification) {
            if (preg_match(self::EMAIL_PATTERN, $recipient)) {
                Notification::route(config('raven.default.email'), $recipient)->notify($notification);

                return true;
            }

            Log::warning("Skipping recipient: \"$recipient\" is not a valid email address.");

            return false;
        }

        if ($notification instanceof SmsNotification) {
            if (preg_match(self::PHONE_PATTERN, $recipient)) {
                Notification::route(config('raven.default.sms'), $recipient)->notify($notification);

                return true;
            }

            Log::warning("Skipping recipient: \"$recipient\" is not a valid phone number.");

            return false;
        }

        return false;
    }
}
