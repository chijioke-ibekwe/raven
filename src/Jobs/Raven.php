<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
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

class Raven implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    const EMAIL_PATTERN = '#^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$#';

    const PHONE_PATTERN = '#^\+?[0-9\s\-()]+$#';

    public function __construct(public readonly Scroll $scroll)
    {
        $queue = config('raven.customizations.queue_name');
        if (! is_null($queue)) {
            $this->onQueue($queue);
        }
    }

    /**
     * @throws Throwable
     */
    public function handle(): void
    {
        $scroll = $this->scroll;
        $context_name = $scroll->getContextName();

        $contextConfig = config("notification-contexts.$context_name");

        throw_if(is_null($contextConfig), RavenContextNotFoundException::class,
            "Notification context with name $context_name does not exist");

        $context = NotificationContext::fromConfig($context_name, $contextConfig);

        if (! $context->active) {
            Log::info("Notification context $context_name is inactive. Skipping notification.");

            return;
        }

        $this->sendNotifications($scroll, $context);
    }

    /**
     * @throws Throwable
     */
    private function sendNotifications(Scroll $scroll, NotificationContext $context): void
    {
        $channels = $context->channels;
        $recipients = $scroll->getRecipients();

        foreach ($channels as $channel) {
            $channel_type = ChannelType::tryFrom(strtoupper($channel));

            if (is_null($channel_type)) {
                throw new RavenInvalidDataException("Notification context has an invalid channel: $channel");
            }

            Log::info("Processing notification for context $context->name through channel $channel_type->name");

            $notification = $channel_type->createNotification($scroll, $context);
            $notification->validateNotification();

            Log::info("Sending notification for context $context->name through channel $channel_type->name");

            try {
                $this->dispatchChannel($scroll, $recipients, $notification);
                RavenNotificationSent::dispatch($scroll, $context, $channel_type->name);
            } catch (Throwable $e) {
                RavenNotificationFailed::dispatch($scroll, $context, $channel_type->name, $e);
                throw $e;
            }
        }
    }

    private function dispatchChannel(Scroll $scroll, array $recipients, RavenNotification $notification): void
    {
        if (! $scroll->getHasOnDemand()) {
            Notification::send($recipients, $notification);

            return;
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                Notification::send([$recipient], $notification);

                continue;
            }

            $this->resolveRouteWithNotification($recipient, $notification);
        }
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

            return;
        }
    }
}
