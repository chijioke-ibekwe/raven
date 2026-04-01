<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotification;
use ChijiokeIbekwe\Raven\Notifications\RavenNotification;
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
        public readonly Scroll $scroll,
        public readonly NotificationContext $context,
        public readonly ChannelType $channelType,
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
            $this->sendToRecipients($notification);
            RavenNotificationSent::dispatch($this->scroll, $this->context, $this->channelType->name);
        } catch (Throwable $e) {
            RavenNotificationFailed::dispatch($this->scroll, $this->context, $this->channelType->name, $e);
            throw $e;
        }
    }

    /**
     * @throws RavenDeliveryException
     */
    private function sendToRecipients(RavenNotification $notification): void
    {
        $recipients = $this->scroll->getRecipients();
        $failures = [];

        foreach ($recipients as $recipient) {
            try {
                $this->sendToRecipient($recipient, $notification);
            } catch (Throwable $e) {
                Log::error("Failed to send {$this->channelType->name} notification to recipient: {$e->getMessage()}");
                $failures[] = ['recipient' => $recipient, 'exception' => $e];
            }
        }

        if (! empty($failures)) {
            $total = count($recipients);
            $failed = count($failures);
            throw RavenDeliveryException::fromFailures(
                "Failed to deliver {$this->channelType->name} notification for context {$this->context->name} to {$failed} of {$total} recipients",
                $failures
            );
        }
    }

    private function sendToRecipient(mixed $recipient, RavenNotification $notification): void
    {
        if (! is_string($recipient)) {
            Notification::send([$recipient], $notification);

            return;
        }

        $this->resolveRouteWithNotification($recipient, $notification);
    }

    private function resolveRouteWithNotification(string $recipient, RavenNotification $notification): void
    {
        if ($notification instanceof EmailNotification) {
            if (preg_match(self::EMAIL_PATTERN, $recipient)) {
                Notification::route(config('raven.default.email'), $recipient)->notify($notification);
            } else {
                Log::warning("Skipping recipient: \"$recipient\" is not a valid email address.");
            }

            return;
        }

        if ($notification instanceof SmsNotification) {
            if (preg_match(self::PHONE_PATTERN, $recipient)) {
                Notification::route(config('raven.default.sms'), $recipient)->notify($notification);
            } else {
                Log::warning("Skipping recipient: \"$recipient\" is not a valid phone number.");
            }
        }
    }
}
