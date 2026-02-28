<?php

namespace ChijiokeIbekwe\Raven\Listeners;

use ChijiokeIbekwe\Raven\Data\NotificationContext;
use ChijiokeIbekwe\Raven\Data\Scroll;
use ChijiokeIbekwe\Raven\Enums\ChannelType;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Events\RavenNotificationFailed;
use ChijiokeIbekwe\Raven\Events\RavenNotificationSent;
use ChijiokeIbekwe\Raven\Exceptions\RavenEntityNotFoundException;
use ChijiokeIbekwe\Raven\Exceptions\RavenInvalidDataException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Notifications\INotificationSender;
use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use ChijiokeIbekwe\Raven\Services\ChannelSenderFactory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class RavenListener
{
    const EMAIL_PATTERN = '#^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$#';

    const PHONE_PATTERN = '#^\+?[0-9\s\-()]+$#';

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @throws \Throwable
     */
    public function handle(Raven $event): void
    {
        $scroll = $event->scroll;
        $context_name = $scroll->getContextName();

        $contextConfig = config("notification-contexts.$context_name");

        throw_if(is_null($contextConfig), RavenEntityNotFoundException::class, "Notification context with name $context_name does not exist");

        $context = NotificationContext::fromConfig($context_name, $contextConfig);

        if (! $context->active) {
            Log::info("Notification context $context_name is inactive. Skipping notification.");

            return;
        }

        $this->sendNotifications($scroll, $context);
    }

    /**
     * @throws \Throwable
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
