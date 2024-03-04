<?php

namespace ChijiokeIbekwe\Raven\Listeners;

use ChijiokeIbekwe\Raven\Data\NotificationData;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Exceptions\RavenEntityNotFoundException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Notifications\DatabaseNotificationSender;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use ChijiokeIbekwe\Raven\Services\ChannelSenderFactory;

/**
 *
 */
class RavenListener
{
    const EMAIL_PATTERN = '#^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$#';
    const PHONE_PATTERN = '#^\+?[0-9\s-()]+$#';
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     * @throws \Throwable
     */
    public function handle(Raven $event): void
    {
        $data = $event->notificationData;
        $context_name = $data->getContextName();

        $context = NotificationContext::where('name', $context_name)->first();

        throw_if(is_null($context), RavenEntityNotFoundException::class, "Notification context with name $context_name does not exist");

        $this->sendNotifications($data, $context);
    }

    private function sendNotifications(NotificationData $data, NotificationContext $context)
    {
        $factory = new ChannelSenderFactory($data, $context);
        $channels = $context->notification_channels;

        foreach($channels as $channel){
            $channel_type = $channel->type;

            Log::info("Processing notification for context $context->name through channel $channel_type->name");

            $channel_sender = $factory->getSender($channel_type);

            $recipients = $data->getRecipients();

            if(!$channel_sender) {
                Log::error("Notification channel $channel_type->name is not currently supported");
                continue;
            }

            Log::info("Sending notification for context $context->name through channel $channel_type->name");

            if(!$data->getHasOnDemand()) {
                Notification::send($recipients, $channel_sender);
                continue;
            }
            
            foreach($recipients as $recipient) {
                if(gettype($recipient) !== 'string') {
                    Notification::send($recipient, $channel_sender);
                    continue;
                }

                $this->resolveRouteWithChannelSender($recipient, $channel_sender);
            }
        }
    }

    private function resolveRouteWithChannelSender($recipient, $channel_sender) 
    {
        $sender_class = get_class($channel_sender);

        switch($sender_class) {  
            case EmailNotificationSender::class:
                if(preg_match(self::EMAIL_PATTERN, $recipient)) {
                    Notification::route(config('raven.notification-service.email'), $recipient)->notify($channel_sender);
                };
                return;
            case SmsNotificationSender::class:
                if(preg_match(self::PHONE_PATTERN, $recipient)) {
                    Notification::route(config('raven.notification-service.sms'), $recipient)->notify($channel_sender);
                };
                return;
            case DatabaseNotificationSender::class:
                return;
        }
    }
}