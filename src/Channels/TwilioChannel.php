<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\SmsNotificationSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Twilio\Exceptions\RestException;
use Twilio\Rest\Client as TwilioClient;

class TwilioChannel
{
    private TwilioClient $twilio;

    public function __construct()
    {
        $this->twilio = app(TwilioClient::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $smsNotification): void
    {
        if (! $smsNotification instanceof SmsNotificationSender) {
            throw new RavenDeliveryException('TwilioChannel requires an SmsNotificationSender notification');
        }

        $details = $smsNotification->toTwilio($notifiable);

        try {
            $response = $this->twilio->messages->create($details[0], $details[1]);

            if ($response->status === 'failed') {
                Log::error('Twilio SMS delivery failed.', [
                    'sid' => $response->sid,
                    'error_code' => $response->errorCode,
                    'error_message' => $response->errorMessage,
                ]);
                throw new RavenDeliveryException(
                    'Twilio SMS delivery failed with error code '.$response->errorCode
                );
            } else {
                Log::info('Twilio SMS accepted.', [
                    'sid' => $response->sid,
                    'status' => $response->status,
                ]);
            }
        } catch (RestException $e) {
            Log::error('Twilio API error.', [
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
