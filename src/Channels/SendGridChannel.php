<?php

namespace ChijiokeIbekwe\Raven\Channels;

use ChijiokeIbekwe\Raven\Notifications\EmailNotificationSender;
use Illuminate\Support\Facades\Log;
use SendGrid;

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
    public function send(mixed $notifiable, EmailNotificationSender $emailNotification): void
    {
        $email = $emailNotification->toSendgrid($notifiable);
        $email->setClickTracking(true, true);
        $email->setOpenTracking(true, '--sub--');
        $sender = config('raven.customizations.mail.from');
        $email->setFrom($sender['address'], $sender['name']);

        $response = $this->sendGrid->send($email);

        if ($response->statusCode() >= '200' && $response->statusCode() < '300') {
            Log::info('Mail success response: '.$response->body());
        }
    }
}
