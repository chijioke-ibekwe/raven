<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Exception;
use Illuminate\Support\Facades\Log;
use SendGrid;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;

class SendGridChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  EmailNotificationSender $sender
     * @param  SendGrid  $sendGrid
     * @return void
     */
    public function send(mixed $notifiable, EmailNotificationSender $sender, SendGrid $sendGrid): void
    {

        try {
            $email = $sender->toSendgrid($notifiable);
            $email->setClickTracking(true, true);
            $email->setOpenTracking(true, "--sub--");
            $email->setFrom(config('raven.mail.from.address'), config('raven.mail.from.name'));

            $response = $sendGrid->send($email);

            if ($response->statusCode() != '202') {
                Log::info("Mail success response: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error("Failed sending mail: " . $e->getMessage());
        }
    }
}