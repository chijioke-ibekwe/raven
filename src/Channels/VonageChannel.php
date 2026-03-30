<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\SmsNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;
use Vonage\Client as VonageClient;

class VonageChannel
{
    private VonageClient $vonage;

    public function __construct()
    {
        $this->vonage = app(VonageClient::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $smsNotification): void
    {
        if (! $smsNotification instanceof SmsNotification) {
            throw new RavenDeliveryException('VonageChannel requires an SmsNotification notification');
        }

        $text = $smsNotification->toVonage($notifiable);

        try {
            $response = $this->vonage->sms()->send($text)->current();
        } catch (Throwable $e) {
            Log::error('Vonage API error.', [
                'message' => $e->getMessage(),
            ]);
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->getStatus() === 0) {
            Log::info('Vonage SMS delivered successfully.', [
                'message_id' => $response->getMessageId(),
            ]);
        } else {
            Log::error('Vonage SMS delivery failed.', [
                'status' => $response->getStatus(),
                'message_id' => $response->getMessageId(),
            ]);
            throw new RavenDeliveryException(
                'Vonage SMS delivery failed with status code '.$response->getStatus()
            );
        }
    }
}
