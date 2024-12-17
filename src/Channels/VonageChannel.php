<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Exception;
use Illuminate\Notifications\Notification;
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
     *
     * @param  mixed  $notifiable
     * @param  Notification $smsNotification
     * @return void
     */
    public function send(mixed $notifiable, Notification $smsNotification): void
    {
        try {
            $text = $smsNotification->toVonage($notifiable);
            $response = $this->vonage->sms()->send($text)->current();

            if($response->getStatus() === 0) {
                Log::info("SMS delivered successfully.", [
                    'message_id' => $response->getMessageId()
                ]);
            } else {
                Log::error("SMS delivery failed with status code " . $response->getStatus(), [
                    'message_id' => $response->getMessageId()
                ]);
            }

        } catch (Exception $e) {
            Log::error("SMS sending error occurred: " . $e->getMessage());
        }
    }
}