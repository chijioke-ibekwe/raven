<?php

namespace ChijiokeIbekwe\Messenger\Channels;

use Exception;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use SendGrid;

class SendGridChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  Notification  $notification
     * @return void
     */
    public function send(mixed $notifiable, Notification $notification): void
    {

        try {
            $email = $notification->toSendgrid($notifiable);
            $email->setClickTracking(true, true);
            $email->setOpenTracking(true, "--sub--");
            $email->setFrom(config('messenger.mail.from.address'), config('messenger.mail.from.name'));

            $sendgrid = new SendGrid(config('messenger.api-key.sendgrid'));
            $response = $sendgrid->send($email);

            if ($response->statusCode() != '202') {
                Log::info("Mail success response: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error("Failed sending mail: " . $e->getMessage());
        }
    }
}