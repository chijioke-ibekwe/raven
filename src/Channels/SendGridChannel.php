<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Exceptions\RavenDeliveryException;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use SendGrid;
use Throwable;

class SendGridChannel
{
    private SendGrid $sendGrid;

    public function __construct()
    {
        $this->sendGrid = app(SendGrid::class);
    }

    /**
     * Send the given notification.
     */
    public function send(mixed $notifiable, Notification $emailNotification): void
    {
        if (! $emailNotification instanceof EmailNotificationSender) {
            throw new RavenDeliveryException('SendGridChannel requires an EmailNotificationSender notification');
        }

        $email = $emailNotification->toSendgrid($notifiable);
        $email->setClickTracking(true, true);
        $email->setOpenTracking(true, '--sub--');
        $sender = config('raven.customizations.mail.from');
        $email->setFrom($sender['address'], $sender['name']);

        try {
            $response = $this->sendGrid->send($email);
        } catch (Throwable $e) {
            Log::error('SendGrid API error.', [
                'message' => $e->getMessage(),
            ]);
            throw new RavenDeliveryException($e->getMessage(), $e->getCode(), $e);
        }

        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            Log::info('SendGrid mail delivered successfully.', [
                'status_code' => $response->statusCode(),
            ]);
        } else {
            Log::error('SendGrid mail delivery failed.', [
                'status_code' => $response->statusCode(),
                'body' => $response->body(),
            ]);
            throw new RavenDeliveryException(
                'SendGrid mail delivery failed with status code '.$response->statusCode()
            );
        }
    }
}
