<?php

namespace ChijiokeIbekwe\Messenger\Services;

use ChijiokeIbekwe\Messenger\Enums\ChannelType;
use ChijiokeIbekwe\Messenger\Notifications\DatabaseNotificationSender;
use ChijiokeIbekwe\Messenger\Notifications\EmailNotificationSender;
use ChijiokeIbekwe\Messenger\Data\NotificationData;
use ChijiokeIbekwe\Messenger\Models\NotificationContext;
use ChijiokeIbekwe\Messenger\Notifications\SmsNotificationSender;

class NotificationSenderFactory
{

    /**
     * @var array
     */
    private array $sender_store;


    /**
     * @param NotificationData $notificationDTO
     * @param NotificationContext $notificationContext
     */
    public function __construct(private readonly NotificationData    $notificationDTO,
                                private readonly NotificationContext $notificationContext)
    {

        $email_sender = new EmailNotificationSender($this->notificationDTO, $this->notificationContext);
        $sms_sender = new SmsNotificationSender($this->notificationDTO, $this->notificationContext);
        $database_sender = new DatabaseNotificationSender($this->notificationDTO, $this->notificationContext);


        $this->sender_store = [
            'EMAIL' => $email_sender,
            'SMS' => null,
            'DATABASE' => $database_sender,
        ];
    }

    /**
     * Supplies the correct notification sender class using the channel
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