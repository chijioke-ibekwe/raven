<?php

namespace ChijiokeIbekwe\Raven\Listeners;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use ChijiokeIbekwe\Raven\Events\Raven;
use ChijiokeIbekwe\Raven\Exceptions\RavenEntityNotFoundException;
use ChijiokeIbekwe\Raven\Models\NotificationContext;
use ChijiokeIbekwe\Raven\Services\ChannelSenderFactory;

/**
 *
 */
class RavenListener
{
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

        throw_if(is_null($context), RavenEntityNotFoundException::class,
            "Notification context with name $context_name does not exist");

        $channels = $context->notification_channels;

        $factory = new ChannelSenderFactory($data, $context);

        foreach($channels as $channel){
            $channel_type = $channel->type;

            Log::debug("Processing notification for context $context_name through channel $channel_type->name");

            $channel_sender = $factory->getSender($channel_type);

            $recipients = $data->getRecipients();

            if($channel_sender){
                Log::debug("Sending notification for context $context_name through channel $channel_type->name");
                Notification::send($recipients, $channel_sender);
            } else {
                Log::error("Notification channel $channel_type->name is not currently supported");
            }

        }
    }
}