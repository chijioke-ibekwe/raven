<?php

namespace ChijiokeIbekwe\Raven\Jobs;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Exceptions\RavenContextNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Notifications\INotificationSender;
use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use ChijiokeIbekwe\Raven\Services\ChannelSenderFactory;
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
        $factory = new ChannelSenderFactory($scroll, $context);
        $channels = $context->channels;
        $recipients = $scroll->getRecipients();

        foreach ($channels as $channel) {
            $channel_type = ChannelType::tryFrom(strtoupper($channel));

            if (is_null($channel_type)) {
                throw new RavenInvalidDataException("Notification context has an invalid channel: $channel");
            }

            Log::info("Processing notification for context $context->name through channel $channel_type->name");

            $channel_sender = $factory->getSender($channel_type);

            Log::info("Sending notification for context $context->name through channel $channel_type->name");

            try {
                $this->dispatchChannel($scroll, $recipients, $channel_sender);
                RavenNotificationSent::dispatch($scroll, $context, $channel_type->name);
            } catch (Throwable $e) {
                RavenNotificationFailed::dispatch($scroll, $context, $channel_type->name, $e);
                throw $e;
            }
        }
    }

    private function dispatchChannel(Scroll $scroll, array $recipients, INotificationSender $channel_sender): void
    {
        if (! $scroll->getHasOnDemand()) {
            Notification::send($recipients, $channel_sender);

            return;
        }

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                Notification::send([$recipient], $channel_sender);

                continue;
            }

            $this->resolveRouteWithChannelSender($recipient, $channel_sender);
        }
    }

    private function resolveRouteWithChannelSender(string $recipient, INotificationSender $channel_sender): void
    {
        if ($channel_sender instanceof EmailNotificationSender) {
            if (preg_match(self::EMAIL_PATTERN, $recipient)) {
                Notification::route(config('raven.default.email'), $recipient)->notify($channel_sender);
            } else {
                Log::warning("Skipping recipient: \"$recipient\" is not a valid email address.");
            }

            return;
        }

        if ($channel_sender instanceof SmsNotificationSender) {
            if (preg_match(self::PHONE_PATTERN, $recipient)) {
                Notification::route(config('raven.default.sms'), $recipient)->notify($channel_sender);
            } else {
                Log::warning("Skipping recipient: \"$recipient\" is not a valid phone number.");
            }

            return;
        }
    }
}
