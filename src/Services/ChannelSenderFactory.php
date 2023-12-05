<?php

namespace ChijiokeIbekwe\Messenger\Services;

use ChijiokeIbekwe\Messenger\Enums\ChannelType;
use ChijiokeIbekwe\Messenger\Notifications\DatabaseNotificationSender;
use ChijiokeIbekwe\Messenger\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Messenger\Data\NotificationData;
use ChijiokeIbekwe\Messenger\Models\NotificationContext;
use ChijiokeIbekwe\Messenger\Notifications\SmsNotificationSender;

class ChannelSenderFactory
{

    /**
     * @var array
     */
    private array $sender_store;


    /**
     * @param NotificationData $notificationData
     * @param NotificationContext $notificationContext
     */
    public function __construct(private readonly NotificationData    $notificationData,
                                private readonly NotificationContext $notificationContext)
    {

        $email_sender = new EmailNotificationSender($this->notificationData, $this->notificationContext);
        $sms_sender = new SmsNotificationSender($this->notificationData, $this->notificationContext);
        $database_sender = new DatabaseNotificationSender($this->notificationData, $this->notificationContext);


        $this->sender_store = [
            'EMAIL' => $email_sender,
            'SMS' => null,
            'DATABASE' => $database_sender,
        ];
    }

    /**
     * Supplies the correct notification channel sender class using the channel
     * @param ChannelType $channel
     * @return mixed
     * @throws \Throwable
     */
    public function getSender(ChannelType $channel): mixed
    {
        $sender = data_get($this->sender_store, $channel->name);

        if($sender){
            $sender->validateNotification();
        }

        return $sender;
    }

}