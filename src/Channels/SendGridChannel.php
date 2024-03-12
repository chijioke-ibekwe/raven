<?php

namespace ChijiokeIbekwe\Raven\Channels;

use Exception;
use Illuminate\Support\Facades\Log;
use SendGrid;
use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;

class SendGridChannel
{
    private SendGrid $sendGrid;

    public function __construct()
    {
        $this->sendGrid = app(SendGrid::class);
    }

    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  EmailNotificationSender $emailNotification
     * @return void
     */
    public function send(mixed $notifiable, EmailNotificationSender $emailNotification): void
    {
        try {
            $email = $emailNotification->toSendgrid($notifiable);
            $email->setClickTracking(true, true);
            $email->setOpenTracking(true, "--sub--");
            $sender = config('raven.customizations.mail.from');
            $email->setFrom($sender['address'], $sender['name']);

            $response = $this->sendGrid->send($email);

            if($response->statusCode() >= '200' && $response->statusCode() < 300) {
                Log::info("Mail success response: " . $response->body());
            }

        } catch (Exception $e) {
            Log::error("Failed sending mail: " . $e->getMessage());
        }
    }
}