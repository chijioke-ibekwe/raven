<?php

namespace ChijiokeIbekwe\Messenger\Channels;

use Exception;
use Illuminate\Support\Facades\Log;
use SendGrid;
use ChijiokeIbekwe\Messenger\Notifications\EmailNotificationSender;

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
            $email->setFrom(config('messenger.mail.from.address'), config('messenger.mail.from.name'));

            $response = $sendGrid->send($email);

            if ($response->statusCode() != '202') {
                Log::info("Mail success response: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error("Failed sending mail: " . $e->getMessage());
        }
    }
}