<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use Illuminate\Support\Facades\Log;
use Vonage\Client;

class VonageChannel
{
    private Client $vonage;

    public function __construct()
    {
        $this->vonage = app(Client::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, SmsNotificationSender $smsNotification): void
    {
        $text = $smsNotification->toVonage($notifiable);
        $response = $this->vonage->sms()->send($text)->current();

        if ($response->getStatus() === 0) {
            Log::info('SMS delivered successfully.', [
                'message_id' => $response->getMessageId(),
            ]);
        } else {
            Log::error('SMS delivery failed with status code '.$response->getStatus(), [
                'message_id' => $response->getMessageId(),
            ]);
        }
    }
}
